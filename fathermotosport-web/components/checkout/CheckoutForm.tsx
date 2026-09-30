"use client";

import { useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { ShieldCheck, Tag, Truck } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { PaymentMethods } from "./PaymentMethods";
import { PaypalButton } from "./PaypalButton";
import { OrderSummary } from "./OrderSummary";
import { useCartStore, cartWeightKg } from "@/store/cartStore";
import { useAuthStore } from "@/store/authStore";
import { createOrder, createExpressOrder } from "@/lib/api/orders";
import { createPaypalOrder } from "@/lib/api/payments";
import { validateCoupon } from "@/lib/api/coupons";
import { calculateShipping } from "@/lib/api/shipping";
import {
  SHIPPING_COUNTRIES,
  countryFlag,
  shippingCountryName,
} from "@/lib/data/shippingCountries";
import { validateCheckout, hasErrors, type FieldErrors } from "@/lib/validators";
import { cn, formatPrice } from "@/lib/utils";
import type { PaymentMethod, ShippingQuote } from "@/lib/types";
import toast from "react-hot-toast";

// Métodos de pago habilitados. Los que no estén acá se muestran en gris
// ("Próximamente") y el checkout ofrece coordinar el pago por WhatsApp.
// Para habilitar otro: agregarlo a esta lista (ej. ["paypal", "stripe"]).
const ENABLED_PAYMENT_METHODS: readonly PaymentMethod[] = ["paypal"];
const WHATSAPP_URL = "https://wa.me/59168736384";

export function CheckoutForm() {
  const t = useTranslations("checkout");
  const tAuth = useTranslations("auth");
  const tCart = useTranslations("cart");
  const tCountries = useTranslations("countries");
  const locale = useLocale();
  const router = useRouter();
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal());
  const clearCart = useCartStore((s) => s.clearCart);
  const user = useAuthStore((s) => s.user);
  const isAuth = useAuthStore((s) => s.isAuth);

  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    // Código ISO, igual que el selector de países del registro.
    country: "BO" as string,
    state: "",
    city: "",
    address_line: "",
    reference: "",
  });
  const [method, setMethod] = useState<PaymentMethod>(ENABLED_PAYMENT_METHODS[0] ?? "paypal");
  const [coupon, setCoupon] = useState("");
  const [discount, setDiscount] = useState(0);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [loading, setLoading] = useState(false);
  const [shipping, setShipping] = useState<ShippingQuote | null>(null);
  const [shippingLoading, setShippingLoading] = useState(false);
  const [optionId, setOptionId] = useState<number | null>(null);

  // Lista fija de 40 destinos (América y Europa), con el nombre en el idioma activo
  // y ordenada según ese idioma. El registro sigue usando la lista completa del mundo.
  const countries = useMemo(
    () =>
      SHIPPING_COUNTRIES.map((c) => ({ code: c.code, label: tCountries(c.code) })).sort((a, b) =>
        a.label.localeCompare(b.label, locale)
      ),
    [tCountries, locale]
  );
  // Nombre traducido para lo que ve el cliente.
  const countryLabel = useMemo(
    () => countries.find((c) => c.code === form.country)?.label ?? form.country,
    [countries, form.country]
  );
  // Nombre canónico en español: es lo que se guarda en la dirección del pedido,
  // para que el panel no reciba el país en tres idiomas distintos.
  const countryName = shippingCountryName(form.country) ?? form.country;

  const methodEnabled = ENABLED_PAYMENT_METHODS.includes(method);
  // PayPal no usa el formulario de datos: la dirección la elige el cliente en PayPal.
  const isPaypal = method === "paypal";
  const weightKg = useMemo(() => cartWeightKg(items), [items]);
  const options = shipping?.options ?? [];
  const selectedOption = options.find((o) => o.id === optionId) ?? null;
  const shippingCost = selectedOption ? parseFloat(selectedOption.price) : null;

  // Recalcula el envío cada vez que cambian el país destino o los items del carrito.
  useEffect(() => {
    if (!form.country || !items.length) {
      setShipping(null);
      return;
    }
    let cancelled = false;
    setShippingLoading(true);
    calculateShipping({
      countryCode: form.country,
      items: items.map((i) => ({ variantId: i.variantId, quantity: i.quantity })),
      weightKg,
    })
      .then((quote) => {
        if (cancelled) return;
        setShipping(quote);
        // Queda elegida la opción más barata; si la anterior sigue disponible, se respeta.
        setOptionId((current) =>
          quote.options.some((o) => o.id === current) ? current : quote.options[0]?.id ?? null
        );
      })
      .catch(() => {
        if (cancelled) return;
        setShipping(null);
        setOptionId(null);
      })
      .finally(() => {
        if (!cancelled) setShippingLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [form.country, items, weightKg]);

  // Autocompleta datos si el usuario está logueado.
  useEffect(() => {
    if (user) {
      setForm((f) => ({
        ...f,
        first_name: user.first_name ?? "",
        last_name: user.last_name ?? "",
        email: user.email,
        phone: user.phone ?? "",
      }));
    }
  }, [user]);

  const showWelcomeCoupon = useMemo(
    () => isAuth && user && !user.loyalty_discount_used,
    [isAuth, user]
  );

  const applyCoupon = async () => {
    if (!coupon.trim()) return;
    const res = await validateCoupon(coupon.trim(), subtotal);
    if (res.valid) {
      setDiscount(res.discount);
      toast.success(tCart("coupon_applied", { amount: res.discount }));
    } else {
      setDiscount(0);
      toast.error(res.message);
    }
  };

  /**
   * PayPal Express: el pedido se crea solo con los items y el envío elegido.
   * El nombre, el email y la dirección los aporta PayPal y el backend los guarda
   * al capturar el pago, así que acá no se pide ningún dato más.
   */
  const pagarConPaypal = async () => {
    if (!selectedOption) {
      toast.error(t("shipping_unavailable", { country: countryLabel }));
      return;
    }

    setLoading(true);
    try {
      const { order } = await createExpressOrder({
        items: items.map((i) => ({ variant_id: i.variantId, quantity: i.quantity })),
        shipping_country_code: form.country,
        shipping_option_id: selectedOption.id,
      });

      const paypal = await createPaypalOrder(order.id, order.access_token ?? undefined);
      if (!paypal.approval_url) throw new Error(t("paypal_no_approval_url"));

      // El pedido ya existe y el stock quedó reservado: el carrito se vacía acá,
      // antes de salir del sitio hacia PayPal.
      clearCart();
      toast.success(t("redirecting_paypal"));
      window.location.href = paypal.approval_url;
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } }).response?.data?.message ??
        t("order_error");
      toast.error(message);
      setLoading(false);
    }
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!items.length) {
      toast.error(t("empty_cart_error"));
      return;
    }

    if (isPaypal) {
      await pagarConPaypal();
      return;
    }

    const validation = validateCheckout({
      first_name: form.first_name,
      last_name: form.last_name,
      // Con sesión iniciada el email sale de la cuenta y no se valida acá.
      email: isAuth ? undefined : form.email,
      phone: form.phone,
      country: form.country,
      city: form.city,
      address_line: form.address_line,
    });
    setErrors(validation);
    if (hasErrors(validation)) {
      // Mensaje del primer campo que falta, en vez del genérico: antes el cliente
      // veía "revisá los campos" sin saber cuál.
      const primerError = Object.values(validation).find(Boolean);
      toast.error(primerError ?? t("form_errors"));
      document.querySelector<HTMLElement>(`[name="${Object.keys(validation)[0]}"]`)?.focus();
      return;
    }
    if (!items.length) {
      toast.error(t("empty_cart_error"));
      return;
    }

    setLoading(true);
    try {
      const res = await createOrder({
        guest_email: isAuth ? undefined : form.email,
        items: items.map((i) => ({ variant_id: i.variantId, quantity: i.quantity })),
        address: {
          full_name: `${form.first_name} ${form.last_name}`.trim(),
          phone: form.phone,
          // La dirección guarda el nombre del país (como hasta ahora); el código ISO
          // solo se usa para calcular el envío.
          country: countryName,
          state: form.state || undefined,
          city: form.city,
          address_line: form.address_line,
          reference: form.reference || undefined,
        },
        payment_method: method,
        shipping_option_id: selectedOption?.id,
        shipping_country_code: selectedOption ? form.country : undefined,
        coupon_code: coupon.trim() || undefined,
      });

      // El pedido ya existe y el stock quedó descontado.
      // (PayPal no pasa por acá: usa pagarConPaypal con el endpoint Express.)
      clearCart();

      toast.success(t("order_created"));
      router.push(
        `/checkout/success?order=${res.order.id}&number=${res.order.order_number}` +
          (res.order.access_token ? `&t=${res.order.access_token}` : "")
      );
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } }).response?.data?.message ??
        t("order_error");
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_320px]">
      <div className="space-y-6">
        {/* Sin sesión: se ofrece entrar o registrarse antes de pagar, así el pedido
            queda asociado a la cuenta. Con sesión iniciada no aparece nada. */}
        {!isAuth && (
          <p className="rounded-xl border border-white/10 bg-brand-card px-4 py-3 text-sm text-brand-muted">
            {t("account_have_account")}{" "}
            <Link
              href={`/login?redirect=/checkout`}
              className="font-semibold text-brand-red transition hover:brightness-125"
            >
              {t("account_login_link")}
            </Link>
            {" · "}
            <Link
              href={`/register?redirect=/checkout`}
              className="font-semibold text-brand-white transition hover:text-brand-red"
            >
              {t("account_register_link")}
            </Link>
          </p>
        )}

        {/* El método de pago va primero: de él depende qué datos hacen falta. */}
        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">{t("payment_method")}</h3>
          <PaymentMethods
            value={method}
            onChange={setMethod}
            enabled={ENABLED_PAYMENT_METHODS}
            comingSoonLabel={t("payments_coming_soon")}
          />
          {!methodEnabled && (
            <div className="mt-3 rounded-xl border border-brand-gold/30 bg-brand-gold/5 p-4">
              <p className="text-sm font-semibold text-brand-gold">{t("coming_soon_whatsapp")}</p>
              <a
                href={WHATSAPP_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110"
              >
                {t("write_whatsapp")}
              </a>
            </div>
          )}
          {isPaypal && (
            <p className="mt-3 text-xs text-brand-muted">{t("express_subtitle")}</p>
          )}
        </section>

        {/* Con PayPal no se piden datos del cliente: los aporta PayPal al pagar. */}
        {!isPaypal && (
        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">{t("your_data")}</h3>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label={t("first_name")}
              name="first_name"
              value={form.first_name}
              onChange={(e) => setForm({ ...form, first_name: e.target.value })}
              error={errors.first_name}
            />
            <Input
              label={t("last_name")}
              name="last_name"
              value={form.last_name}
              onChange={(e) => setForm({ ...form, last_name: e.target.value })}
              error={errors.last_name}
            />
            <Input
              label={tAuth("email")}
              name="email"
              type="email"
              className={cn("col-span-2", isAuth && "cursor-not-allowed opacity-70")}
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              readOnly={isAuth}
              hint={isAuth ? t("email_from_account") : undefined}
              error={errors.email}
            />
          </div>
        </section>

        )}

        {!isPaypal && (
        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">{t("shipping_address")}</h3>
          <div className="grid grid-cols-2 gap-3">
            <Input label={t("phone")} name="phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} error={errors.phone} />
            <Input label={t("state")} name="state" value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })} />
            <Input label={t("city")} name="city" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} error={errors.city} />
            <Input label={t("address")} name="address_line" value={form.address_line} onChange={(e) => setForm({ ...form, address_line: e.target.value })} error={errors.address_line} />
            <Input label={t("reference")} name="reference" className="col-span-2" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} />
          </div>
        </section>
        )}

        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">{tCart("shipping")}</h3>

          {/* El país define la tarifa. Es el único dato que se pide con PayPal. */}
          <div className="mb-3">
            <label className="mb-1.5 block text-sm font-medium text-brand-white" htmlFor="checkout-country">
              {isPaypal ? t("express_country_question") : t("country")}
            </label>
            <select
              id="checkout-country"
              name="country"
              value={form.country}
              onChange={(e) => setForm({ ...form, country: e.target.value })}
              className="input-brand"
            >
              {countries.map((c) => (
                <option key={c.code} value={c.code}>
                  {countryFlag(c.code)} {c.label}
                </option>
              ))}
            </select>
          </div>

          {shippingLoading ? (
            <p className="text-sm text-brand-muted">{t("shipping_calculating")}</p>
          ) : shipping?.available ? (
            <div className="space-y-2">
              <p className="text-xs text-brand-muted">
                {t("shipping_weight", { weight: shipping.weight_kg })}
              </p>
              {options.map((option) => {
                const selected = option.id === optionId;
                const price = parseFloat(option.price);
                return (
                  <label
                    key={option.id}
                    className={cn(
                      "flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition",
                      selected
                        ? "border-brand-red bg-brand-red/5"
                        : "border-white/10 bg-brand-card hover:border-white/30"
                    )}
                  >
                    <input
                      type="radio"
                      name="shipping_option"
                      value={option.id}
                      checked={selected}
                      onChange={() => setOptionId(option.id)}
                      className="h-4 w-4 accent-brand-red"
                    />
                    <Truck size={18} className={selected ? "text-brand-red" : "text-brand-muted"} />
                    <span className="flex-1">
                      <span className="block text-sm font-semibold text-brand-white">
                        {option.method_name}
                      </span>
                      {option.estimated_days_min !== null && option.estimated_days_max !== null && (
                        <span className="block text-xs text-brand-muted">
                          {t("shipping_days", {
                            min: option.estimated_days_min,
                            max: option.estimated_days_max,
                          })}
                        </span>
                      )}
                    </span>
                    <span
                      className={cn(
                        "text-sm font-bold",
                        price > 0 ? "text-brand-white" : "text-cat-boots"
                      )}
                    >
                      {price > 0 ? formatPrice(option.price) : t("free")}
                    </span>
                  </label>
                );
              })}
            </div>
          ) : (
            <div className="rounded-xl border border-brand-gold/30 bg-brand-gold/5 p-4">
              <p className="text-sm font-semibold text-brand-gold">
                {t("shipping_unavailable", { country: countryLabel })}
              </p>
              <a
                href={WHATSAPP_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110"
              >
                {t("write_whatsapp")}
              </a>
            </div>
          )}
        </section>

        {/* El cupón no aplica al flujo de PayPal: no hay paso intermedio donde
            recalcular el total antes de mandar el monto a PayPal. */}
        {!isPaypal && (
        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">{t("coupon")}</h3>
          {showWelcomeCoupon && (
            <p className="mb-2 flex items-center gap-1.5 text-sm text-brand-gold">
              <Tag size={14} /> {t("welcome_coupon")}
            </p>
          )}
          <div className="flex gap-2">
            <Input placeholder={tCart("coupon_placeholder")} value={coupon} onChange={(e) => setCoupon(e.target.value)} />
            <Button type="button" variant="glass" onClick={applyCoupon}>
              {tCart("apply")}
            </Button>
          </div>
        </section>
        )}
      </div>

      <div className="lg:sticky lg:top-20 lg:self-start">
        <OrderSummary
          discount={discount}
          shipping={shippingCost}
          shippingMethod={selectedOption?.method_name}
          shippingNote={shippingLoading ? t("shipping_calculating") : t("shipping_to_coordinate")}
        >
          {methodEnabled ? (
            <>
              {isPaypal ? (
                <>
                  <PaypalButton
                    className="mt-4"
                    label={t("pay_with")}
                    ariaLabel={t("pay_with_paypal")}
                    loading={loading}
                    disabled={!selectedOption}
                  />
                  <p className="mt-2 text-center text-[11px] text-brand-muted">
                    {t("express_address_note")}
                  </p>
                </>
              ) : (
                <Button type="submit" variant="primary" className="mt-4 w-full" loading={loading}>
                  {t("confirm_order")}
                </Button>
              )}
              <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
                <ShieldCheck size={14} className="text-cat-boots" /> {t("ssl_secure")}
              </p>
            </>
          ) : (
            <>
              <a
                href={WHATSAPP_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-4 flex w-full items-center justify-center rounded-xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white transition hover:brightness-110"
              >
                {t("contact_whatsapp")}
              </a>
              <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
                <ShieldCheck size={14} className="text-cat-boots" /> {t("payments_coming_soon")}
              </p>
            </>
          )}
        </OrderSummary>
      </div>
    </form>
  );
}

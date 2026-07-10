"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { ShieldCheck, Tag } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { PaymentMethods } from "./PaymentMethods";
import { OrderSummary } from "./OrderSummary";
import { useCartStore } from "@/store/cartStore";
import { useAuthStore } from "@/store/authStore";
import { createOrder } from "@/lib/api/orders";
import { validateCoupon } from "@/lib/api/coupons";
import { validateCheckout, hasErrors, type FieldErrors } from "@/lib/validators";
import type { PaymentMethod } from "@/lib/types";
import toast from "react-hot-toast";

// Los pagos online están deshabilitados hasta contar con credenciales reales
// de las pasarelas. Mientras tanto se coordina el pago por WhatsApp.
// Para reactivar: poner PAYMENTS_ENABLED = true.
const PAYMENTS_ENABLED = false;
const WHATSAPP_URL = "https://wa.me/59168736384";

const countries = [
  { value: "Bolivia", label: "Bolivia" },
  { value: "Brasil", label: "Brasil" },
  { value: "Argentina", label: "Argentina" },
  { value: "Chile", label: "Chile" },
  { value: "Peru", label: "Perú" },
  { value: "Colombia", label: "Colombia" },
  { value: "Mexico", label: "México" },
  { value: "Ecuador", label: "Ecuador" },
  { value: "Paraguay", label: "Paraguay" },
  { value: "Uruguay", label: "Uruguay" },
  { value: "Venezuela", label: "Venezuela" },
  { value: "España", label: "España" },
  { value: "USA", label: "Estados Unidos" },
  { value: "Otro", label: "Otro país" },
];

export function CheckoutForm() {
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
    country: "Bolivia" as string,
    state: "",
    city: "",
    address_line: "",
    reference: "",
  });
  const [method, setMethod] = useState<PaymentMethod>("paypal");
  const [coupon, setCoupon] = useState("");
  const [discount, setDiscount] = useState(0);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [loading, setLoading] = useState(false);

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
      toast.success(`Cupón aplicado: −$${res.discount}`);
    } else {
      setDiscount(0);
      toast.error(res.message);
    }
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const validation = validateCheckout({
      full_name: `${form.first_name} ${form.last_name}`.trim(),
      email: isAuth ? undefined : form.email,
      phone: form.phone,
      country: form.country,
      city: form.city,
      address_line: form.address_line,
    });
    setErrors(validation);
    if (hasErrors(validation)) {
      toast.error("Revisa los campos del formulario.");
      return;
    }
    if (!items.length) {
      toast.error("Tu carrito está vacío.");
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
          country: form.country,
          state: form.state || undefined,
          city: form.city,
          address_line: form.address_line,
          reference: form.reference || undefined,
        },
        payment_method: method,
        coupon_code: coupon.trim() || undefined,
      });

      clearCart();
      toast.success("¡Pedido creado!");
      router.push(`/checkout/success?order=${res.order.id}&number=${res.order.order_number}`);
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "No se pudo crear el pedido.";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_320px]">
      <div className="space-y-6">
        {!isAuth && (
          <section>
            <h3 className="mb-3 text-lg font-bold text-brand-white">Tus datos</h3>
            <div className="grid grid-cols-2 gap-3">
              <Input label="Nombre" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} error={errors.full_name} />
              <Input label="Apellido" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
              <Input label="Email" type="email" className="col-span-2" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email} />
            </div>
          </section>
        )}

        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">Dirección de envío</h3>
          <div className="grid grid-cols-2 gap-3">
            <div className="col-span-2">
              <label className="mb-1.5 block text-sm font-medium text-brand-white">País</label>
              <select
                value={form.country}
                onChange={(e) => setForm({ ...form, country: e.target.value })}
                className="input-brand"
              >
                {countries.map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label}
                  </option>
                ))}
              </select>
            </div>
            <Input label="Teléfono" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} error={errors.phone} />
            <Input label="Departamento / Estado" value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })} />
            <Input label="Ciudad" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} error={errors.city} />
            <Input label="Dirección" value={form.address_line} onChange={(e) => setForm({ ...form, address_line: e.target.value })} error={errors.address_line} />
            <Input label="Referencia (opcional)" className="col-span-2" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} />
          </div>
        </section>

        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">Método de pago</h3>
          {PAYMENTS_ENABLED ? (
            <PaymentMethods value={method} onChange={setMethod} />
          ) : (
            <div className="rounded-xl border border-brand-gold/30 bg-brand-gold/5 p-4">
              <p className="text-sm font-semibold text-brand-gold">
                Próximamente — Contactanos por WhatsApp para coordinar tu pago
              </p>
              <a
                href={WHATSAPP_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110"
              >
                Escribir por WhatsApp
              </a>
              <div className="pointer-events-none mt-4 select-none opacity-40" aria-hidden>
                <PaymentMethods value={method} onChange={setMethod} />
              </div>
            </div>
          )}
        </section>

        <section>
          <h3 className="mb-3 text-lg font-bold text-brand-white">Cupón</h3>
          {showWelcomeCoupon && (
            <p className="mb-2 flex items-center gap-1.5 text-sm text-brand-gold">
              <Tag size={14} /> ¡Tienes un cupón de bienvenida de $5! Revísalo en tu perfil.
            </p>
          )}
          <div className="flex gap-2">
            <Input placeholder="Código de cupón" value={coupon} onChange={(e) => setCoupon(e.target.value)} />
            <Button type="button" variant="glass" onClick={applyCoupon}>
              Aplicar
            </Button>
          </div>
        </section>
      </div>

      <div className="lg:sticky lg:top-20 lg:self-start">
        <OrderSummary discount={discount}>
          {PAYMENTS_ENABLED ? (
            <>
              <Button type="submit" variant="primary" className="mt-4 w-full" loading={loading}>
                Confirmar pedido
              </Button>
              <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
                <ShieldCheck size={14} className="text-cat-boots" /> SSL · Pago 100% seguro
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
                Contactanos por WhatsApp para coordinar tu pago
              </a>
              <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
                <ShieldCheck size={14} className="text-cat-boots" /> Pagos online próximamente
              </p>
            </>
          )}
        </OrderSummary>
      </div>
    </form>
  );
}

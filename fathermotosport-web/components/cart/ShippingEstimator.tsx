"use client";

import { useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Truck } from "lucide-react";
import { useCartStore, cartWeightKg } from "@/store/cartStore";
import { calculateShipping } from "@/lib/api/shipping";
import { SHIPPING_COUNTRIES, countryFlag } from "@/lib/data/shippingCountries";
import { whatsappUrl } from "@/lib/api/storeSettings";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";
import { cn, formatPrice } from "@/lib/utils";
import type { ShippingQuote } from "@/lib/types";

interface ShippingEstimatorProps {
  /** Envío más barato disponible para el país elegido; null si no hay tarifa o aún no carga. */
  onCheapestChange?: (price: number | null) => void;
}

/**
 * Muestra en el carrito lo mismo que verá el cliente en el checkout (misma API, mismas
 * tarifas): opciones, plazo y precio por país. Es solo informativo; la opción que se
 * cobra se elige y se valida en el checkout.
 */
export function ShippingEstimator({ onCheapestChange }: ShippingEstimatorProps) {
  const t = useTranslations("cart");
  const tCheckout = useTranslations("checkout");
  const tCountries = useTranslations("countries");
  const locale = useLocale();
  const whatsappHref = whatsappUrl(useStoreSettings().whatsappDigits);
  const items = useCartStore((s) => s.items);

  const [country, setCountry] = useState("BO");
  const [quote, setQuote] = useState<ShippingQuote | null>(null);
  const [loading, setLoading] = useState(false);

  const countries = useMemo(
    () =>
      SHIPPING_COUNTRIES.map((c) => ({ code: c.code, label: tCountries(c.code) })).sort((a, b) =>
        a.label.localeCompare(b.label, locale)
      ),
    [tCountries, locale]
  );
  const countryLabel = countries.find((c) => c.code === country)?.label ?? country;

  useEffect(() => {
    if (!items.length) {
      setQuote(null);
      onCheapestChange?.(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    calculateShipping({
      countryCode: country,
      items: items.map((i) => ({ variantId: i.variantId, quantity: i.quantity })),
      weightKg: cartWeightKg(items),
    })
      .then((result) => {
        if (cancelled) return;
        setQuote(result);
        const prices = result.options.map((o) => parseFloat(o.price));
        onCheapestChange?.(result.available && prices.length ? Math.min(...prices) : null);
      })
      .catch(() => {
        if (cancelled) return;
        setQuote(null);
        onCheapestChange?.(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
    // onCheapestChange es un setState del padre: su identidad no cambia el resultado.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [country, items]);

  return (
    <div className="rounded-xl border border-white/10 bg-brand-dark/40 p-4">
      <p className="mb-3 flex items-center gap-2 text-sm font-semibold text-brand-white">
        <Truck size={16} className="text-cat-boots" />
        {t("shipping_estimate_title")}
      </p>

      <label className="sr-only" htmlFor="cart-shipping-country">
        {tCheckout("country")}
      </label>
      <select
        id="cart-shipping-country"
        value={country}
        onChange={(e) => setCountry(e.target.value)}
        className="input-brand mb-3"
      >
        {countries.map((c) => (
          <option key={c.code} value={c.code}>
            {countryFlag(c.code)} {c.label}
          </option>
        ))}
      </select>

      {loading ? (
        <p className="text-sm text-brand-muted">{tCheckout("shipping_calculating")}</p>
      ) : quote?.available ? (
        <ul className="space-y-2">
          {quote.options.map((option, index) => {
            const price = parseFloat(option.price);
            return (
              <li
                key={option.id}
                className={cn(
                  "flex items-center justify-between gap-3 rounded-lg border px-3 py-2",
                  index === 0 ? "border-brand-red/40 bg-brand-red/5" : "border-white/10"
                )}
              >
                <span>
                  <span className="block text-sm font-semibold text-brand-white">
                    {option.method_name}
                  </span>
                  {option.estimated_days_min !== null && option.estimated_days_max !== null && (
                    <span className="block text-xs text-brand-muted">
                      {tCheckout("shipping_days", {
                        min: option.estimated_days_min,
                        max: option.estimated_days_max,
                      })}
                    </span>
                  )}
                </span>
                <span className={cn("text-sm font-bold", price > 0 ? "text-brand-white" : "text-cat-boots")}>
                  {price > 0 ? formatPrice(option.price) : tCheckout("free")}
                </span>
              </li>
            );
          })}
        </ul>
      ) : (
        <div className="text-sm">
          <p className="text-brand-gold">{tCheckout("shipping_unavailable", { country: countryLabel })}</p>
          {whatsappHref && (
            <a
              href={whatsappHref}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-2 inline-block font-semibold text-brand-red hover:brightness-125"
            >
              {tCheckout("write_whatsapp")}
            </a>
          )}
        </div>
      )}
    </div>
  );
}

"use client";

import { useTranslations } from "next-intl";
import { isGatewayAdvertised } from "@/lib/data/paymentMethods";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";

interface MarqueeItem {
  label: string;
  /** Si es pill, se resalta con etiqueta dorada + ícono. */
  pill?: boolean;
  icon?: string;
  /** Nombre de marca: nunca se traduce (translate="no"). */
  brand?: boolean;
}

export function MarqueeBar() {
  const t = useTranslations("benefits");
  const ajustes = useStoreSettings();
  const mercadopago = ajustes.mercadopago || ajustes.manualPaymentMethods.includes("mercadopago_mx");
  const paypal = isGatewayAdvertised("paypal") || ajustes.manualPaymentMethods.includes("paypal_manual");

  const items: MarqueeItem[] = [
    { label: t("free_shipping"), pill: true },
    { label: t("international_shipping"), pill: true},
    // Solo lo que el checkout cobra hoy (PayPal y MercadoPago vuelven solos al reactivarse).
    ...(paypal ? [{ label: "PayPal", brand: true }] : []),
    { label: "Apple Pay", brand: true },
    { label: "Google Pay", brand: true },
    { label: "Visa · Mastercard", brand: true },
    ...(mercadopago ? [{ label: "Mercado Pago", brand: true }] : []),
    { label: "USDT", brand: true },
    { label: "Bitcoin", brand: true },
    { label: t("certified_helmets") },
    { label: "AGV", brand: true },
    { label: "Shoei", brand: true },
    { label: "Shark", brand: true },
  ];
  const row = [...items, ...items];

  return (
    <div className="overflow-hidden border-y border-brand-red/20 bg-brand-red/[0.07] py-3">
      <div className="flex w-max animate-marquee gap-8 whitespace-nowrap">
        {row.map((item, i) => (
          <span key={i} className="flex items-center gap-8 text-sm font-semibold text-brand-white/80">
            {item.pill ? (
              <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-gold px-3 py-1 text-xs font-bold text-brand-carbon">
                {item.icon && <span aria-hidden>{item.icon}</span>}
                {item.label}
              </span>
            ) : item.brand ? (
              <span translate="no">{item.label}</span>
            ) : (
              item.label
            )}
            <span className="text-brand-red">·</span>
          </span>
        ))}
      </div>
    </div>
  );
}

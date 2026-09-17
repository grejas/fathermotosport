"use client";

import { useTranslations } from "next-intl";

interface MarqueeItem {
  label: string;
  /** Si es pill, se resalta con etiqueta dorada + ícono. */
  pill?: boolean;
  icon?: string;
}

export function MarqueeBar() {
  const t = useTranslations("benefits");
  const tNav = useTranslations("nav");

  const items: MarqueeItem[] = [
    { label: t("free_shipping"), pill: true },
    { label: t("international_shipping"), pill: true},
    { label: "PayPal" },
    { label: "Stripe" },
    { label: "MercadoPago" },
    { label: `${tNav("helmets")} ECE 22.06` },
    { label: "AGV" },
    { label: "Shoei" },
    { label: "Shark" },
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

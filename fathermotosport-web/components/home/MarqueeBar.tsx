"use client";

import { useTranslations } from "next-intl";

export function MarqueeBar() {
  const t = useTranslations("benefits");
  const tNav = useTranslations("nav");

  const tokens = [
    t("free_shipping"),
    "PayPal",
    "Stripe",
    "MercadoPago",
    `${tNav("helmets")} ECE 22.06`,
    "AGV",
    "Shoei",
    "Shark",
  ];
  const row = [...tokens, ...tokens];
  return (
    <div className="overflow-hidden border-y border-brand-red/20 bg-brand-red/[0.07] py-3">
      <div className="flex w-max animate-marquee gap-8 whitespace-nowrap">
        {row.map((token, i) => (
          <span key={i} className="flex items-center gap-8 text-sm font-semibold text-brand-white/80">
            {token}
            <span className="text-brand-red">·</span>
          </span>
        ))}
      </div>
    </div>
  );
}

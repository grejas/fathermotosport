"use client";

import { useTranslations } from "next-intl";
import { Truck, ShieldCheck, Lock, MessageCircle } from "lucide-react";

const benefits = [
  { icon: Truck, titleKey: "free_shipping", descKey: "free_shipping_desc" },
  { icon: ShieldCheck, titleKey: "warranty", descKey: "warranty_desc" },
  { icon: Lock, titleKey: "secure_payment", descKey: "secure_payment_desc" },
  { icon: MessageCircle, titleKey: "support", descKey: "support_desc" },
] as const;

export function FooterStrip() {
  const t = useTranslations("benefits");

  return (
    <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {benefits.map((b) => {
          const Icon = b.icon;
          return (
            <div
              key={b.titleKey}
              className="flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-brand-card p-5"
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-red/10 text-brand-red">
                <Icon size={22} />
              </span>
              <div>
                <p className="text-sm font-bold text-brand-white">{t(b.titleKey)}</p>
                <p className="text-xs text-brand-muted">{t(b.descKey)}</p>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}

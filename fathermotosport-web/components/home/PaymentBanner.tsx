"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";

export function PaymentBanner() {
  const t = useTranslations("home");
  const ajustes = useStoreSettings();
  const mercadopago = ajustes.mercadopago || ajustes.manualPaymentMethods.includes("mercadopago_mx");
  const medios = ["Visa", "Mastercard", "Apple Pay", "Google Pay", ...(mercadopago ? ["Mercado Pago"] : []), "USDT", "Bitcoin"];

  return (
    <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
      <div className="flex flex-col items-center justify-between gap-6 rounded-2xl border border-white/10 bg-gradient-to-r from-brand-dark to-brand-card p-8 md:flex-row">
        <div>
          <h3 className="text-xl font-bold text-brand-white">{t("pay_in_installments")}</h3>
          <p className="mt-1 text-sm text-brand-muted">
            {t("payment_desc")}
          </p>
        </div>
        <div className="flex flex-wrap items-center justify-center gap-3">
          {medios.map((m) => (
            <span
              key={m}
              translate="no"
              className="rounded-lg border border-white/10 bg-brand-carbon px-4 py-2 text-sm font-semibold text-brand-white"
            >
              {m}
            </span>
          ))}
        </div>
        <Link href="/checkout">
          <Button variant="gold">{t("view_payment_methods")}</Button>
        </Link>
      </div>
    </section>
  );
}

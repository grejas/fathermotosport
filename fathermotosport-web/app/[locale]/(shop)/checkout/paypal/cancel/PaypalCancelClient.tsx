"use client";

import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { MessageCircle, XCircle } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";

const WHATSAPP_URL = "https://wa.me/59168736384";

/**
 * El cliente canceló en PayPal. El pedido ya existe como pendiente de pago:
 * se puede reintentar desde "Mis pedidos" o coordinar por WhatsApp.
 */
export function PaypalCancelClient() {
  const t = useTranslations("checkout");
  const orderId = useSearchParams().get("order");

  return (
    <div className="mx-auto flex min-h-[70vh] max-w-lg flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-red/10">
        <XCircle size={32} className="text-brand-red" />
      </span>
      <h1 className="text-2xl font-bold text-brand-white">{t("paypal_cancelled_title")}</h1>
      <p className="text-sm text-brand-muted">{t("paypal_cancelled_text")}</p>
      {orderId && (
        <p className="text-xs text-brand-muted">{t("paypal_order_reference", { id: orderId })}</p>
      )}
      <div className="mt-2 flex flex-col gap-3 sm:flex-row">
        <Link href="/orders">
          <Button variant="primary">{t("paypal_see_orders")}</Button>
        </Link>
        <Link href="/catalog">
          <Button variant="glass">{t("go_to_catalog")}</Button>
        </Link>
      </div>
      <a
        href={WHATSAPP_URL}
        target="_blank"
        rel="noopener noreferrer"
        className="mt-2 inline-flex items-center gap-2 text-sm font-medium text-brand-muted transition hover:text-cat-boots"
      >
        <MessageCircle size={16} />
        {t("write_whatsapp")}
      </a>
    </div>
  );
}

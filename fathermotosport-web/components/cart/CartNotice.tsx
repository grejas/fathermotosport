"use client";

import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Info } from "lucide-react";

/**
 * ?aviso= con el que la página de pago de un pedido pendiente manda al carrito cuando
 * ese pedido ya se canceló (por ejemplo, se abrió el correo de recuperación tarde).
 */
export const AVISO_PEDIDO_CANCELADO = "pedido-cancelado";

/** Aviso arriba del carrito según el ?aviso= de la URL. Sin aviso conocido, nada. */
export function CartNotice() {
  const t = useTranslations("cart");
  const aviso = useSearchParams().get("aviso");

  if (aviso !== AVISO_PEDIDO_CANCELADO) return null;

  return (
    <div
      role="status"
      className="mx-auto mb-6 flex max-w-2xl items-start gap-3 rounded-xl border border-white/10 bg-brand-card px-4 py-3 text-left text-sm text-brand-muted"
    >
      <Info size={18} className="mt-0.5 shrink-0 text-brand-red" />
      <p>{t("order_cancelled_notice")}</p>
    </div>
  );
}

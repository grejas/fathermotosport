"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useCartStore } from "@/store/cartStore";
import { formatPrice, getImageUrl } from "@/lib/utils";

interface OrderSummaryProps {
  discount?: number;
  /** Costo de envío calculado; null = todavía no hay tarifa para ese destino. */
  shipping?: number | null;
  /** Texto a mostrar en la fila de envío cuando no hay un monto (calculando, a coordinar…). */
  shippingNote?: string;
  /** Nombre del método elegido ("Express DHL"), bajo la etiqueta de envío. */
  shippingMethod?: string | null;
  children?: React.ReactNode;
}

export function OrderSummary({
  discount = 0,
  shipping = null,
  shippingNote,
  shippingMethod,
  children,
}: OrderSummaryProps) {
  const t = useTranslations("checkout");
  const tCart = useTranslations("cart");
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal());
  const total = Math.max(0, subtotal - discount) + (shipping ?? 0);

  return (
    <div className="rounded-2xl border border-white/10 bg-brand-card p-5">
      <h3 className="mb-4 text-lg font-bold text-brand-white">{t("order_summary")}</h3>

      <div className="max-h-64 space-y-3 overflow-y-auto">
        {items.map((item) => (
          <div key={item.variantId} className="flex gap-3">
            <div className="relative h-12 w-12 shrink-0 overflow-hidden rounded-md bg-brand-dark">
              {item.image && (
                <Image src={getImageUrl(item.image)} alt={item.name} fill className="object-cover" sizes="48px" />
              )}
              <span className="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-brand-red px-1 text-[10px] font-bold text-white">
                {item.quantity}
              </span>
            </div>
            <div className="flex-1">
              <p className="line-clamp-1 text-sm text-brand-white">{item.name}</p>
              <p className="text-xs text-brand-muted">
                {[item.size, item.color].filter(Boolean).join(" · ")}
              </p>
            </div>
            <span className="text-sm text-brand-white">{formatPrice(item.price * item.quantity)}</span>
          </div>
        ))}
      </div>

      <div className="mt-4 space-y-2 border-t border-white/10 pt-4 text-sm">
        <div className="flex justify-between text-brand-muted">
          <span>{tCart("subtotal")}</span>
          <span className="text-brand-white">{formatPrice(subtotal)}</span>
        </div>
        {discount > 0 && (
          <div className="flex justify-between text-cat-boots">
            <span>{tCart("discount")}</span>
            <span>−{formatPrice(discount)}</span>
          </div>
        )}
        <div className="flex justify-between text-brand-muted">
          <span>
            {tCart("shipping")}
            {shippingMethod ? <span className="block text-xs">{shippingMethod}</span> : null}
          </span>
          {shipping === null ? (
            <span className="text-right text-xs text-brand-muted">{shippingNote ?? "—"}</span>
          ) : shipping === 0 ? (
            <span className="font-semibold text-cat-boots">{t("free")}</span>
          ) : (
            <span className="text-brand-white">{formatPrice(shipping)}</span>
          )}
        </div>
        <div className="flex justify-between border-t border-white/10 pt-2 text-base font-bold text-brand-white">
          <span>{tCart("total")}</span>
          <span>{formatPrice(total)}</span>
        </div>
      </div>

      {children}
    </div>
  );
}

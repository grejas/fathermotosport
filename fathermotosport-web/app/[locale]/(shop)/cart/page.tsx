"use client";

import { Suspense, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import Image from "next/image";
import { Minus, Plus, Trash2, ShoppingBag } from "lucide-react";
import { useCartStore } from "@/store/cartStore";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Spinner } from "@/components/ui/Spinner";
import { CartNotice } from "@/components/cart/CartNotice";
import { formatPrice, getImageUrl } from "@/lib/utils";
import { useMounted } from "@/lib/hooks/useMounted";
import { validateCoupon } from "@/lib/api/coupons";
import toast from "react-hot-toast";

export default function CartPage() {
  const t = useTranslations("cart");
  const tCheckout = useTranslations("checkout");
  const tCommon = useTranslations("common");
  const mounted = useMounted();
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal());
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const [coupon, setCoupon] = useState("");
  const [discount, setDiscount] = useState(0);

  const apply = async () => {
    if (!coupon.trim()) return;
    const res = await validateCoupon(coupon.trim(), subtotal);
    if (res.valid) {
      setDiscount(res.discount);
      toast.success(t("coupon_applied", { amount: res.discount }));
    } else {
      setDiscount(0);
      toast.error(res.message);
    }
  };

  // Hasta el montaje el store persistido aún no está disponible en el cliente;
  // evitamos renderizar contenido divergente respecto al servidor.
  if (!mounted) {
    return (
      <div className="flex min-h-[70vh] items-center justify-center pt-20">
        <Spinner size={32} />
      </div>
    );
  }

  if (!items.length) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
        {/* Suspense: CartNotice lee ?aviso= con useSearchParams. */}
        <Suspense fallback={null}>
          <CartNotice />
        </Suspense>
        <ShoppingBag size={56} className="text-brand-muted opacity-40" />
        <h1 className="text-2xl font-bold text-brand-white">{t("empty_title")}</h1>
        <Link href="/catalog">
          <Button variant="primary">{tCommon("explore_products")}</Button>
        </Link>
      </div>
    );
  }

  const total = Math.max(0, subtotal - discount);

  return (
    <div className="mx-auto max-w-7xl px-4 pb-16 pt-24 sm:px-6">
      <Suspense fallback={null}>
        <CartNotice />
      </Suspense>
      <h1 className="mb-8 text-3xl font-extrabold text-brand-white">{t("my_cart")}</h1>

      <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div className="space-y-4">
          {items.map((item) => (
            <div
              key={item.variantId}
              className="flex gap-4 rounded-2xl border border-white/[0.06] bg-brand-card p-4"
            >
              <div className="relative h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-brand-dark">
                {item.image && (
                  <Image src={getImageUrl(item.image)} alt={item.name} fill className="object-cover" sizes="96px" />
                )}
              </div>

              <div className="flex flex-1 flex-col">
                <p className="font-semibold text-brand-white">{item.name}</p>
                <p className="text-sm text-brand-muted">
                  {[item.size, item.color].filter(Boolean).join(" · ")}
                </p>
                <p className="text-sm text-brand-muted">{formatPrice(item.price)} c/u</p>

                <div className="mt-auto flex items-center justify-between pt-2">
                  <div className="flex items-center rounded-lg border border-white/10">
                    <button onClick={() => updateQuantity(item.variantId, item.quantity - 1)} className="p-2 text-brand-muted hover:text-brand-white">
                      <Minus size={14} />
                    </button>
                    <span
                      className="min-w-[32px] text-center text-sm text-brand-white"
                      aria-label={`${t("quantity")}: ${item.quantity}`}
                    >
                      {item.quantity}
                    </span>
                    <button onClick={() => updateQuantity(item.variantId, item.quantity + 1)} className="p-2 text-brand-muted hover:text-brand-white">
                      <Plus size={14} />
                    </button>
                  </div>

                  <div className="flex items-center gap-4">
                    <span className="font-bold text-brand-white">
                      {formatPrice(item.price * item.quantity)}
                    </span>
                    <button
                      onClick={() => removeItem(item.variantId)}
                      className="text-brand-muted hover:text-brand-red"
                      aria-label={t("remove")}
                    >
                      <Trash2 size={18} />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}

          <div className="flex flex-wrap gap-3">
            <Link href="/catalog">
              <Button variant="glass">{t("continue_shopping")}</Button>
            </Link>
            <Button variant="glass" onClick={clearCart}>
              {t("clear_cart")}
            </Button>
          </div>
        </div>

        <div className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-2xl border border-white/10 bg-brand-card p-6">
            <h3 className="mb-4 text-lg font-bold text-brand-white">{tCheckout("order_summary")}</h3>

            <div className="mb-4 flex gap-2">
              <Input placeholder={t("coupon_placeholder")} value={coupon} onChange={(e) => setCoupon(e.target.value)} />
              <Button variant="glass" onClick={apply}>
                {t("apply")}
              </Button>
            </div>

            <div className="space-y-2 border-t border-white/10 pt-4 text-sm">
              <div className="flex justify-between text-brand-muted">
                <span>{t("subtotal")}</span>
                <span className="text-brand-white">{formatPrice(subtotal)}</span>
              </div>
              {discount > 0 && (
                <div className="flex justify-between text-cat-boots">
                  <span>{t("discount")}</span>
                  <span>−{formatPrice(discount)}</span>
                </div>
              )}
              <div className="flex justify-between text-brand-muted">
                <span>{t("shipping")}</span>
                <span className="font-semibold text-cat-boots">{t("shipping_free")}</span>
              </div>
              <div className="flex justify-between border-t border-white/10 pt-2 text-base font-bold text-brand-white">
                <span>{t("total")}</span>
                <span>{formatPrice(total)}</span>
              </div>
            </div>

            <Link href="/checkout" className="mt-5 block">
              <Button variant="primary" className="w-full">
                {t("checkout")}
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

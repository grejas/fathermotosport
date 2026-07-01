"use client";

import { useState } from "react";
import Link from "next/link";
import Image from "next/image";
import { Minus, Plus, Trash2, ShoppingBag } from "lucide-react";
import { useCartStore } from "@/store/cartStore";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Spinner } from "@/components/ui/Spinner";
import { formatPrice, getImageUrl } from "@/lib/utils";
import { useMounted } from "@/lib/hooks/useMounted";
import { validateCoupon } from "@/lib/api/coupons";
import toast from "react-hot-toast";

export default function CartPage() {
  const mounted = useMounted();
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal());
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);
  const [coupon, setCoupon] = useState("");
  const [discount, setDiscount] = useState(0);

  const apply = async () => {
    if (!coupon.trim()) return;
    const res = await validateCoupon(coupon.trim(), subtotal);
    if (res.valid) {
      setDiscount(res.discount);
      toast.success(`Cupón aplicado: −$${res.discount}`);
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
        <ShoppingBag size={56} className="text-brand-muted opacity-40" />
        <h1 className="text-2xl font-bold text-brand-white">Tu carrito está vacío</h1>
        <Link href="/catalog">
          <Button variant="primary">Explorar productos</Button>
        </Link>
      </div>
    );
  }

  const total = Math.max(0, subtotal - discount);

  return (
    <div className="mx-auto max-w-7xl px-4 pb-16 pt-24 sm:px-6">
      <h1 className="mb-8 text-3xl font-extrabold text-brand-white">Mi carrito</h1>

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
                    <span className="min-w-[32px] text-center text-sm text-brand-white">{item.quantity}</span>
                    <button onClick={() => updateQuantity(item.variantId, item.quantity + 1)} className="p-2 text-brand-muted hover:text-brand-white">
                      <Plus size={14} />
                    </button>
                  </div>

                  <div className="flex items-center gap-4">
                    <span className="font-bold text-brand-white">
                      {formatPrice(item.price * item.quantity)}
                    </span>
                    <button onClick={() => removeItem(item.variantId)} className="text-brand-muted hover:text-brand-red">
                      <Trash2 size={18} />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}

          <Link href="/catalog">
            <Button variant="glass">Seguir comprando</Button>
          </Link>
        </div>

        <div className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-2xl border border-white/10 bg-brand-card p-6">
            <h3 className="mb-4 text-lg font-bold text-brand-white">Resumen</h3>

            <div className="mb-4 flex gap-2">
              <Input placeholder="Código de cupón" value={coupon} onChange={(e) => setCoupon(e.target.value)} />
              <Button variant="glass" onClick={apply}>
                Aplicar
              </Button>
            </div>

            <div className="space-y-2 border-t border-white/10 pt-4 text-sm">
              <div className="flex justify-between text-brand-muted">
                <span>Subtotal</span>
                <span className="text-brand-white">{formatPrice(subtotal)}</span>
              </div>
              {discount > 0 && (
                <div className="flex justify-between text-cat-boots">
                  <span>Descuento</span>
                  <span>−{formatPrice(discount)}</span>
                </div>
              )}
              <div className="flex justify-between text-brand-muted">
                <span>Envío</span>
                <span className="font-semibold text-cat-boots">$0 · Gratis</span>
              </div>
              <div className="flex justify-between border-t border-white/10 pt-2 text-base font-bold text-brand-white">
                <span>Total</span>
                <span>{formatPrice(total)}</span>
              </div>
            </div>

            <Link href="/checkout" className="mt-5 block">
              <Button variant="primary" className="w-full">
                Continuar al checkout
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

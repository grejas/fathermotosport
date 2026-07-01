"use client";

import Image from "next/image";
import { Minus, Plus, Trash2 } from "lucide-react";
import { useCartStore, type LocalCartItem } from "@/store/cartStore";
import { formatPrice, getImageUrl } from "@/lib/utils";

export function CartItem({ item }: { item: LocalCartItem }) {
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);

  return (
    <div className="flex gap-3 border-b border-white/[0.06] py-4">
      <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-brand-card">
        {item.image ? (
          <Image src={getImageUrl(item.image)} alt={item.name} fill className="object-cover" sizes="80px" />
        ) : (
          <div className="flex h-full items-center justify-center text-xs text-brand-muted">Sin foto</div>
        )}
      </div>

      <div className="flex flex-1 flex-col">
        <p className="line-clamp-2 text-sm font-semibold text-brand-white">{item.name}</p>
        <p className="text-xs text-brand-muted">
          {[item.size, item.color].filter(Boolean).join(" · ")}
        </p>

        <div className="mt-auto flex items-center justify-between pt-2">
          <div className="flex items-center rounded-lg border border-white/10">
            <button
              onClick={() => updateQuantity(item.variantId, item.quantity - 1)}
              className="p-1.5 text-brand-muted hover:text-brand-white"
              aria-label="Reducir"
            >
              <Minus size={14} />
            </button>
            <span className="min-w-[28px] text-center text-sm text-brand-white">{item.quantity}</span>
            <button
              onClick={() => updateQuantity(item.variantId, item.quantity + 1)}
              className="p-1.5 text-brand-muted hover:text-brand-white"
              aria-label="Aumentar"
            >
              <Plus size={14} />
            </button>
          </div>

          <span className="text-sm font-bold text-brand-white">
            {formatPrice(item.price * item.quantity)}
          </span>
        </div>
      </div>

      <button
        onClick={() => removeItem(item.variantId)}
        className="self-start p-1 text-brand-muted transition hover:text-brand-red"
        aria-label="Eliminar"
      >
        <Trash2 size={16} />
      </button>
    </div>
  );
}

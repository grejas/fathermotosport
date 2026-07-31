"use client";

import { useMemo, useState } from "react";
import { Heart, Minus, Plus, Ruler, ShieldCheck, Star, Truck, MessageCircle, Award } from "lucide-react";
import type { Product, ProductVariant } from "@/lib/types";
import { cn, formatPrice, getCategoryColor } from "@/lib/utils";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { SizeGuideModal } from "@/components/product/SizeGuideModal";
import { useCartStore } from "@/store/cartStore";
import { useFavoritesStore } from "@/store/favoritesStore";
import { useAuthStore } from "@/store/authStore";
import toast from "react-hot-toast";

const whatsapp = (process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384").replace(/[^0-9]/g, "");

export function ProductInfo({ product }: { product: Product }) {
  const accent = getCategoryColor(product.category?.slug);
  const variants = useMemo(() => (product.variants ?? []).filter((v) => v.is_active), [product]);

  const sizes = useMemo(() => [...new Set(variants.map((v) => v.size).filter(Boolean))], [variants]);
  const colors = useMemo(() => [...new Set(variants.map((v) => v.color).filter(Boolean))], [variants]);

  const [size, setSize] = useState<string | null>(sizes[0] ?? null);
  const [color, setColor] = useState<string | null>(colors[0] ?? null);
  const [qty, setQty] = useState(1);
  const [sizeGuideOpen, setSizeGuideOpen] = useState(false);

  const selectedVariant: ProductVariant | undefined = useMemo(
    () =>
      variants.find(
        (v) => (size ? v.size === size : true) && (color ? v.color === color : true)
      ) ?? variants[0],
    [variants, size, color]
  );

  const addItem = useCartStore((s) => s.addItem);
  const toggleFav = useFavoritesStore((s) => s.toggle);
  const isFav = useFavoritesStore((s) => s.isFavorite(product.id));
  const isAuth = useAuthStore((s) => s.isAuth);

  // Stock a mostrar: si hay variante seleccionada, su stock; si no, el total
  // de todas las variantes activas (variants ya viene filtrado por is_active).
  const totalStock = variants.reduce((sum, v) => sum + v.stock, 0);
  const displayStock = selectedVariant ? selectedVariant.stock : totalStock;
  const inStock = displayStock > 0;
  const rating = Math.round(product.rating_avg ?? 0);

  const handleAdd = () => {
    if (!selectedVariant || !inStock) {
      toast.error("Sin stock disponible");
      return;
    }
    addItem(product, selectedVariant, qty);
    toast.success(`${product.name} agregado al carrito`);
  };

  return (
    <div className="space-y-5">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide" style={{ color: accent }}>
          {product.brand?.name}
        </p>
        <h1 className="mt-1 text-3xl font-extrabold text-brand-white">{product.name}</h1>

        <div className="mt-2 flex items-center gap-3">
          <div className="flex">
            {Array.from({ length: 5 }).map((_, i) => (
              <Star
                key={i}
                size={16}
                className={i < rating ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
              />
            ))}
          </div>
          <span className="text-xs text-brand-muted">{product.reviews_count ?? 0} reseñas</span>
          {inStock ? <Badge variant="green">En stock</Badge> : <Badge variant="red">Agotado</Badge>}
        </div>
      </div>

      <div className="flex items-baseline gap-3">
        <span className="text-3xl font-extrabold text-brand-white">
          {formatPrice(product.sale_price ?? product.price)}
        </span>
        {product.sale_price && (
          <>
            <span className="text-lg text-brand-muted line-through">{formatPrice(product.price)}</span>
            <Badge variant="red">-{product.discount_percent}%</Badge>
          </>
        )}
      </div>

      {sizes.length > 0 && (
        <div>
          <p className="mb-2 text-sm font-semibold text-brand-white">Talla</p>
          <div className="flex flex-wrap gap-2">
            {sizes.map((s) => {
              const v = variants.find((x) => x.size === s && (color ? x.color === color : true));
              const disabled = !v || v.stock === 0;
              return (
                <button
                  key={s}
                  onClick={() => setSize(s)}
                  disabled={disabled}
                  className={cn(
                    "min-w-[44px] rounded-lg border px-3 py-2 text-sm font-medium transition",
                    size === s
                      ? "border-brand-red bg-brand-red/10 text-brand-white"
                      : "border-white/10 text-brand-muted hover:border-white/30",
                    disabled && "cursor-not-allowed opacity-40 line-through"
                  )}
                >
                  {s}
                </button>
              );
            })}
          </div>
          <button
            onClick={() => setSizeGuideOpen(true)}
            className="mt-2 flex items-center gap-1.5 text-xs font-medium text-brand-muted transition hover:text-brand-white"
          >
            <Ruler size={14} />
            Ver guía de tallas
          </button>
          <SizeGuideModal open={sizeGuideOpen} onClose={() => setSizeGuideOpen(false)} />
        </div>
      )}

      {colors.length > 0 && (
        <div>
          <p className="mb-2 text-sm font-semibold text-brand-white">Color: {color}</p>
          <div className="flex gap-2">
            {colors.map((c) => (
              <button
                key={c}
                onClick={() => setColor(c)}
                title={c ?? ""}
                className={cn(
                  "h-9 rounded-lg border px-3 text-xs transition",
                  color === c
                    ? "border-brand-red bg-brand-red/10 text-brand-white"
                    : "border-white/10 text-brand-muted hover:border-white/30"
                )}
              >
                {c}
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="flex items-center gap-4">
        <div className="flex items-center rounded-lg border border-white/10">
          <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="p-2.5 text-brand-muted hover:text-brand-white">
            <Minus size={16} />
          </button>
          <span className="min-w-[40px] text-center font-semibold text-brand-white">{qty}</span>
          <button
            onClick={() => setQty((q) => Math.min(selectedVariant?.stock ?? 99, q + 1))}
            className="p-2.5 text-brand-muted hover:text-brand-white"
          >
            <Plus size={16} />
          </button>
        </div>
        <span className="text-xs text-brand-muted">
          {displayStock} disponibles
        </span>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row">
        <Button variant="primary" className="flex-1" onClick={handleAdd} disabled={!inStock}>
          Agregar al carrito
        </Button>
        {isAuth && (
          <Button
            variant="glass"
            icon={<Heart size={18} className={isFav ? "fill-brand-red text-brand-red" : ""} />}
            onClick={() => toggleFav(product)}
          >
            {isFav ? "Guardado" : "Favoritos"}
          </Button>
        )}
      </div>

      <a
        href={`https://wa.me/${whatsapp}?text=${encodeURIComponent(`Hola, me interesa el producto: ${product.name}`)}`}
        target="_blank"
        rel="noopener noreferrer"
      >
        <Button variant="glass" className="w-full" icon={<MessageCircle size={18} />}>
          Consultar por WhatsApp
        </Button>
      </a>

      <div className="grid grid-cols-2 gap-3 border-t border-white/10 pt-5 sm:grid-cols-4">
        {[
          { icon: Truck, label: "Envío gratis" },
          { icon: ShieldCheck, label: "Garantía" },
          { icon: Award, label: product.certification ?? "Certificado" },
          { icon: MessageCircle, label: "Soporte" },
        ].map(({ icon: Icon, label }, i) => (
          <div key={i} className="flex flex-col items-center gap-1.5 text-center">
            <Icon size={20} style={{ color: accent }} />
            <span className="text-xs text-brand-muted">{label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

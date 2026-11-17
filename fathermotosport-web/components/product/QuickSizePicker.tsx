"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { Ruler } from "lucide-react";
import { Modal } from "@/components/ui/Modal";
import { Link } from "@/lib/i18n/navigation";
import { useCartStore } from "@/store/cartStore";
import { trackAddToCart } from "@/lib/analytics/events";
import { cn, finalPrice, formatPrice, getImageUrl } from "@/lib/utils";
import type { Product, ProductVariant } from "@/lib/types";

const ORDEN_TALLAS = ["XXXS", "XXS", "XS", "S", "M", "L", "XL", "XXL", "2XL", "XXXL", "3XL", "4XL"];

function rangoTalla(size: string): number {
  const s = size.trim().toUpperCase();
  const i = ORDEN_TALLAS.indexOf(s);
  if (i >= 0) return i;
  const n = parseFloat(s.replace(",", "."));
  return Number.isFinite(n) ? 100 + n : 1000;
}

/** Variantes activas con talla, de menor a mayor. Si hay más de una, el cliente tiene que elegir. */
export function sizedVariants(product: Product): ProductVariant[] {
  return (product.variants ?? [])
    .filter((v) => v.is_active && v.size)
    .sort((a, b) => rangoTalla(a.size ?? "") - rangoTalla(b.size ?? ""));
}

/** true = hay que preguntar la talla antes de agregar (más de una talla activa). */
export function needsSizeChoice(product: Product): boolean {
  return sizedVariants(product).length > 1;
}

/**
 * Selector de talla para "agregar rápido" desde tarjetas (catálogo, relacionados, carrito).
 * Antes el botón "+" agregaba la primera talla con stock sin preguntar, lo que lleva a
 * pedidos con la talla equivocada y devoluciones.
 *
 * El modal va en un portal, pero los eventos de React igual suben por el árbol hasta el
 * <Link> de la tarjeta: el envoltorio corta esa propagación para que no navegue.
 */
export function QuickSizePicker({
  product,
  open,
  onClose,
  origen,
}: {
  product: Product;
  open: boolean;
  onClose: () => void;
  origen?: string;
}) {
  const t = useTranslations("product");
  const tSize = useTranslations("size_picker");
  const addItem = useCartStore((s) => s.addItem);
  const variantes = sizedVariants(product);

  const elegir = (v: ProductVariant) => {
    if (v.stock <= 0) return;
    addItem(product, v, 1);
    trackAddToCart(product, 1);
    toast.success(t("added_to_cart", { name: `${product.name} · ${v.size}` }));
    onClose();
  };

  return (
    <div
      onClick={(e) => {
        e.stopPropagation();
        e.preventDefault();
      }}
      data-origen={origen}
    >
      <Modal open={open} onClose={onClose} title={tSize("title")}>
        <div className="flex items-center gap-3">
          <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-white">
            <Image src={getImageUrl(product.primary_image)} alt={product.name} fill sizes="64px" className="object-contain p-1" />
          </div>
          <div className="min-w-0">
            <p className="line-clamp-2 text-sm font-semibold text-brand-white">{product.name}</p>
            <p className="text-sm font-bold text-brand-gold">{formatPrice(finalPrice(product))}</p>
          </div>
        </div>

        <div className="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4">
          {variantes.map((v) => {
            const agotada = v.stock <= 0;
            return (
              <button
                key={v.id}
                type="button"
                disabled={agotada}
                onClick={() => elegir(v)}
                className={cn(
                  "rounded-lg border px-2 py-3 text-sm font-bold transition",
                  agotada
                    ? "cursor-not-allowed border-white/5 text-white/25 line-through"
                    : "border-white/15 text-brand-white hover:border-brand-red hover:bg-brand-red/10 active:scale-95"
                )}
                aria-label={agotada ? tSize("size_out", { size: v.size ?? "" }) : tSize("size_add", { size: v.size ?? "" })}
              >
                {v.size}
                {!agotada && v.stock <= 2 && (
                  <span className="mt-0.5 block text-[10px] font-semibold text-brand-gold">{tSize("last_units", { n: v.stock })}</span>
                )}
              </button>
            );
          })}
        </div>

        <Link
          href={`/product/${product.slug}`}
          onClick={onClose}
          className="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-muted hover:text-brand-gold"
        >
          <Ruler size={14} /> {tSize("guide")}
        </Link>
      </Modal>
    </div>
  );
}

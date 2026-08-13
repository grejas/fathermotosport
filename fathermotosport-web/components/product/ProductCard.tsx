"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Box, Plus } from "lucide-react";
import type { Product } from "@/lib/types";
import { cn, formatPrice, getCategoryColor, getImageUrl } from "@/lib/utils";
import { useCartStore } from "@/store/cartStore";
import toast from "react-hot-toast";

export function ProductCard({ product }: { product: Product }) {
  const t = useTranslations("product");
  const accent = getCategoryColor(product.category?.slug);
  const addItem = useCartStore((s) => s.addItem);

  // Agotado solo si NINGUNA variante activa tiene stock.
  const isOutOfStock = !product.variants?.some((v) => v.is_active && v.stock > 0);

  const quickAdd = (e: React.MouseEvent) => {
    e.preventDefault();
    const variant = product.variants?.find((v) => v.is_active && v.stock > 0);
    if (!variant) {
      toast.error(t("no_stock"));
      return;
    }
    addItem(product, variant, 1);
    toast.success(t("added_to_cart", { name: product.name }));
  };

  return (
    <Link href={`/product/${product.slug}`} className="group" aria-label={`${t("view_product")}: ${product.name}`}>
      <article
        className="card-product h-full"
        style={{ ["--accent" as string]: accent }}
        onMouseEnter={(e) => (e.currentTarget.style.borderColor = `${accent}55`)}
        onMouseLeave={(e) => (e.currentTarget.style.borderColor = "")}
      >
        <div className="relative aspect-square overflow-hidden bg-brand-dark">
          {product.primary_image ? (
            <Image
              src={getImageUrl(product.primary_image)}
              alt={product.name}
              fill
              sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
              className="object-cover transition-transform duration-500 group-hover:scale-105"
            />
          ) : (
            <div className="flex h-full items-center justify-center">
              <Box size={48} style={{ color: accent }} className="opacity-30" />
            </div>
          )}

          <div className="absolute left-2 top-2 flex flex-col gap-1">
            {isOutOfStock && (
              <span className="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-brand-muted ring-1 ring-white/15">
                {t("out_of_stock")}
              </span>
            )}
            {product.discount_percent > 0 && (
              <span className="badge-red">-{product.discount_percent}%</span>
            )}
            {product.is_new && <span className="badge-gold">{t("new")}</span>}
            {!product.is_new && product.is_popular && <span className="badge-gold">{t("popular")}</span>}
          </div>

          {product.has_3d_model && (
            <span
              className="absolute right-2 top-2 flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-bold text-brand-white backdrop-blur"
              title={t("model_3d_available")}
            >
              <Box size={11} /> 3D
            </span>
          )}
        </div>

        <div className="p-4">
          <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: accent }}>
            {product.brand?.name ?? "—"}
          </p>
          <h3 className="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-brand-white sm:text-base">
            {product.name}
          </h3>

          <div className="mt-3 flex items-center justify-between">
            <div className="flex items-baseline gap-2">
              <span className="text-base font-bold text-brand-white sm:text-lg">
                {formatPrice(product.sale_price ?? product.price)}
              </span>
              {product.sale_price && (
                <span className="text-xs text-brand-muted line-through">
                  {formatPrice(product.price)}
                </span>
              )}
            </div>

            <button
              onClick={quickAdd}
              disabled={isOutOfStock}
              className={cn(
                "flex h-8 w-8 items-center justify-center rounded-lg text-white transition active:scale-90 sm:h-9 sm:w-9",
                isOutOfStock && "cursor-not-allowed opacity-40"
              )}
              style={{ backgroundColor: accent }}
              aria-label={t("add_to_cart")}
            >
              <Plus size={16} className="sm:hidden" />
              <Plus size={18} className="hidden sm:block" />
            </button>
          </div>
        </div>
      </article>
    </Link>
  );
}

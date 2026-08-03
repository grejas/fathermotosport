"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Box, Heart, Plus } from "lucide-react";
import { useFavoritesStore } from "@/store/favoritesStore";
import { useCartStore } from "@/store/cartStore";
import { Button } from "@/components/ui/Button";
import { formatPrice, getCategoryColor, getImageUrl } from "@/lib/utils";
import toast from "react-hot-toast";

export default function FavoritesPage() {
  const t = useTranslations("favorites");
  const tCommon = useTranslations("common");
  const tProduct = useTranslations("product");
  const items = useFavoritesStore((s) => s.items);
  const toggle = useFavoritesStore((s) => s.toggle);
  const addItem = useCartStore((s) => s.addItem);

  if (!items.length) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-brand-card py-16 text-brand-muted">
        <Heart size={48} className="opacity-40" />
        <p>{t("empty")}</p>
        <Link href="/catalog">
          <Button variant="glass" size="sm">
            {tCommon("explore_products")}
          </Button>
        </Link>
      </div>
    );
  }

  const quickAdd = (product: (typeof items)[number]) => {
    const variant = product.variants?.find((v) => v.is_active && v.stock > 0);
    if (!variant) {
      toast.error(tProduct("no_stock"));
      return;
    }
    addItem(product, variant, 1);
    toast.success(tProduct("added_to_cart", { name: product.name }));
  };

  return (
    <div>
      <h1 className="mb-6 text-2xl font-extrabold text-brand-white">{t("my_favorites")}</h1>
      <div className="grid grid-cols-2 gap-5 lg:grid-cols-3">
        {items.map((product) => {
          const accent = getCategoryColor(product.category?.slug);
          return (
            <div key={product.id} className="card-product">
              <Link href={`/product/${product.slug}`}>
                <div className="relative aspect-square overflow-hidden bg-brand-dark">
                  {product.primary_image ? (
                    <Image src={getImageUrl(product.primary_image)} alt={product.name} fill className="object-cover" sizes="33vw" />
                  ) : (
                    <div className="flex h-full items-center justify-center">
                      <Box size={40} style={{ color: accent }} className="opacity-30" />
                    </div>
                  )}
                </div>
              </Link>
              <div className="p-4">
                <p className="line-clamp-1 text-sm font-semibold text-brand-white">{product.name}</p>
                <p className="mt-1 text-lg font-bold text-brand-white">
                  {formatPrice(product.sale_price ?? product.price)}
                </p>
                <div className="mt-3 flex gap-2">
                  <Button variant="primary" size="sm" className="flex-1" icon={<Plus size={16} />} onClick={() => quickAdd(product)}>
                    {t("add")}
                  </Button>
                  <button
                    onClick={() => toggle(product)}
                    className="rounded-lg border border-white/10 p-2 text-brand-red transition hover:bg-white/5"
                    aria-label={t("remove_from_favorites")}
                  >
                    <Heart size={18} className="fill-brand-red" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

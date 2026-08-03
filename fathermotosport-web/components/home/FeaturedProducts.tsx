"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { ArrowRight } from "lucide-react";
import { useFeaturedProducts } from "@/lib/hooks/useProducts";
import { ProductGrid } from "@/components/product/ProductGrid";

export function FeaturedProducts() {
  const t = useTranslations("home");
  const { data, isLoading } = useFeaturedProducts();

  return (
    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <div className="mb-8 flex items-end justify-between">
        <div>
          <h2 className="text-2xl font-bold text-brand-white">{t("featured")}</h2>
          <p className="text-sm text-brand-muted">{t("featured_subtitle")}</p>
        </div>
        <Link href="/catalog" className="flex items-center gap-1 text-sm font-semibold text-brand-red hover:underline">
          {t("view_all")} <ArrowRight size={16} />
        </Link>
      </div>

      <ProductGrid
        products={data ?? []}
        loading={isLoading}
        emptyMessage={t("no_featured")}
      />
    </section>
  );
}

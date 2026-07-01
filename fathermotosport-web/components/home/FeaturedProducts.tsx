"use client";

import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { useFeaturedProducts } from "@/lib/hooks/useProducts";
import { ProductGrid } from "@/components/product/ProductGrid";

export function FeaturedProducts() {
  const { data, isLoading } = useFeaturedProducts();

  return (
    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <div className="mb-8 flex items-end justify-between">
        <div>
          <h2 className="text-2xl font-bold text-brand-white">Destacados</h2>
          <p className="text-sm text-brand-muted">Lo más buscado de la temporada</p>
        </div>
        <Link href="/catalog" className="flex items-center gap-1 text-sm font-semibold text-brand-red hover:underline">
          Ver todo <ArrowRight size={16} />
        </Link>
      </div>

      <ProductGrid
        products={data ?? []}
        loading={isLoading}
        emptyMessage="Aún no hay productos destacados."
      />
    </section>
  );
}

"use client";

import { useQuery } from "@tanstack/react-query";
import type { Product } from "@/lib/types";
import { getRelatedProducts } from "@/lib/api/products";
import { ProductCard } from "./ProductCard";

interface Props {
  categoryId?: number;
  currentSlug: string;
}

export function RelatedProducts({ categoryId, currentSlug }: Props) {
  const { data } = useQuery({
    queryKey: ["related", categoryId, currentSlug],
    queryFn: () => getRelatedProducts(categoryId!, currentSlug),
    enabled: !!categoryId,
  });

  const products = (data ?? []) as Product[];
  if (!products.length) return null;

  return (
    <section className="mt-16">
      <h2 className="mb-6 text-2xl font-bold text-brand-white">También te puede interesar</h2>
      <div className="grid grid-cols-2 gap-5 lg:grid-cols-4">
        {products.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>
    </section>
  );
}

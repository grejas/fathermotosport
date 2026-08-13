"use client";

import { motion } from "framer-motion";
import { useTranslations } from "next-intl";
import type { Product } from "@/lib/types";
import { stagger, slideUp } from "@/animations/variants";
import { Skeleton } from "@/components/ui/Skeleton";
import { ProductCard } from "./ProductCard";

interface ProductGridProps {
  products: Product[];
  loading?: boolean;
  emptyMessage?: string;
}

export function ProductGrid({ products, loading, emptyMessage }: ProductGridProps) {
  const t = useTranslations("catalog");

  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="card-product p-0">
            <Skeleton className="aspect-square w-full rounded-b-none" />
            <div className="space-y-2 p-4">
              <Skeleton className="h-3 w-1/3" />
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-6 w-1/2" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (!products.length) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-brand-muted">
        {emptyMessage ?? t("no_products")}
      </div>
    );
  }

  return (
    <motion.div
      variants={stagger}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-50px" }}
      className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
      {products.map((p) => (
        <motion.div key={p.id} variants={slideUp}>
          <ProductCard product={p} />
        </motion.div>
      ))}
    </motion.div>
  );
}

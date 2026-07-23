"use client";

import { useState } from "react";
import { Eye } from "lucide-react";
import { useProduct } from "@/lib/hooks/useProducts";
import { ProductGallery } from "@/components/product/ProductGallery";
import { Product3DGallery } from "@/components/product/Product3DGallery";
import { ProductInfo } from "@/components/product/ProductInfo";
import { ProductTabs } from "@/components/product/ProductTabs";
import { RelatedProducts } from "@/components/product/RelatedProducts";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";
import { VisorColorsModal } from "@/components/3d/VisorColorsModal";

export function ProductClient({ slug }: { slug: string }) {
  const { data: product, isLoading, isError } = useProduct(slug);
  const [showVisorModal, setShowVisorModal] = useState(false);

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-24 sm:px-6 lg:grid-cols-2">
        <Skeleton className="aspect-square w-full rounded-2xl" />
        <div className="space-y-4">
          <Skeleton className="h-4 w-1/4" />
          <Skeleton className="h-8 w-3/4" />
          <Skeleton className="h-6 w-1/3" />
          <Skeleton className="h-12 w-full" />
        </div>
      </div>
    );
  }

  if (isError || !product) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center text-brand-muted">
        Producto no encontrado.
      </div>
    );
  }

  const isHelmet = product.category?.slug === "cascos";

  return (
    <div className="mx-auto max-w-7xl px-4 pb-16 pt-24 sm:px-6">
      <div className="grid gap-10 lg:grid-cols-2">
        <div>
          {product.has_3d_model && product.model_3d ? (
            <Product3DGallery
              modelUrl={product.model_3d.file_glb_url}
              images={product.images ?? []}
              name={product.name}
              showVisorColors={isHelmet}
            />
          ) : (
            <ProductGallery images={product.images ?? []} name={product.name} />
          )}
          {isHelmet && (
            <Button
              variant="glass"
              size="sm"
              icon={<Eye size={16} />}
              className="mt-4 w-full sm:w-auto"
              onClick={() => setShowVisorModal(true)}
            >
              Ver visores
            </Button>
          )}
        </div>
        <ProductInfo product={product} />
      </div>

      <ProductTabs product={product} />
      <RelatedProducts categoryId={product.category?.id} currentSlug={product.slug} />

      {isHelmet && (
        <VisorColorsModal
          open={showVisorModal}
          onClose={() => setShowVisorModal(false)}
          productVisorColors={product.visor_colors}
        />
      )}
    </div>
  );
}

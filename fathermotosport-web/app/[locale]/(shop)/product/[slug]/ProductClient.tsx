"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Eye } from "lucide-react";
import { useRouter } from "@/lib/i18n/navigation";
import { useProduct } from "@/lib/hooks/useProducts";
import { ProductGallery } from "@/components/product/ProductGallery";
import { Product3DGallery } from "@/components/product/Product3DGallery";
import { SpinGallery } from "@/components/product/SpinGallery";
import { ProductInfo } from "@/components/product/ProductInfo";
import { ProductTabs } from "@/components/product/ProductTabs";
import { RelatedProducts } from "@/components/product/RelatedProducts";
import { ProductFlashPromo } from "@/components/product/ProductFlashPromo";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";
import { VisorColorsModal } from "@/components/3d/VisorColorsModal";
import { translateText } from "@/lib/utils/translate";
import type { ShippingReturnsSummary } from "@/lib/shippingReturns";

export function ProductClient({
  slug,
  shippingReturns,
}: {
  slug: string;
  shippingReturns: ShippingReturnsSummary;
}) {
  const t = useTranslations("product");
  const locale = useLocale();
  const router = useRouter();
  const { data: product, isLoading, isError } = useProduct(slug);
  const [showVisorModal, setShowVisorModal] = useState(false);

  // Si el usuario llegó navegando desde el catálogo, router.back() lo devuelve
  // exactamente a esa página conservando filtros/orden/página (estado local
  // de CatalogClient), a diferencia de un <Link href="/catalog"> que siempre
  // remonta el catálogo desde cero. Si no hay historial previo en la pestaña
  // (entrada directa al producto), cae a /catalog en vez de no hacer nada.
  const handleBackToCatalog = () => {
    if (typeof window !== "undefined" && window.history.length > 1) {
      router.back();
    } else {
      router.push("/catalog");
    }
  };
  const [translated, setTranslated] = useState<{ name: string; description: string | null } | null>(null);

  // Traduce silenciosamente name/description al idioma activo (pt/en) usando la
  // API pública de Google Translate. El contenido en la base de datos vive en
  // español; en 'es' no se traduce nada.
  useEffect(() => {
    if (!product || locale === "es") {
      setTranslated(null);
      return;
    }
    let cancelled = false;
    (async () => {
      const [name, description] = await Promise.all([
        translateText(product.name, locale),
        product.description ? translateText(product.description, locale) : Promise.resolve(product.description),
      ]);
      if (!cancelled) setTranslated({ name, description });
    })();
    return () => {
      cancelled = true;
    };
  }, [product, locale]);

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 pb-16 pt-16 sm:px-6 sm:pt-20 lg:grid-cols-2 lg:gap-10 lg:pt-24">
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
  const displayProduct = translated ? { ...product, name: translated.name, description: translated.description } : product;

  return (
    <div className="mx-auto max-w-7xl px-4 pb-16 pt-16 sm:px-6 sm:pt-20 lg:pt-24">
      <button
        onClick={handleBackToCatalog}
        className="mb-6 inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-brand-white transition-all duration-300 hover:border-brand-red/40 hover:bg-white/10 hover:text-brand-red"
      >
        Volver al catálogo
      </button>

      <ProductFlashPromo categoryId={product.category?.id} categorySlug={product.category?.slug} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-10">
        <div>
          {product.spin_url ? (
            <SpinGallery
              spinUrl={product.spin_url}
              images={product.images ?? []}
              name={displayProduct.name}
            />
          ) : product.has_3d_model && product.model_3d ? (
            <Product3DGallery
              modelUrl={product.model_3d.file_glb_url}
              images={product.images ?? []}
              name={displayProduct.name}
              showVisorColors={isHelmet}
            />
          ) : (
            <ProductGallery images={product.images ?? []} name={displayProduct.name} />
          )}
          {isHelmet && (
            <Button
              variant="glass"
              size="sm"
              icon={<Eye size={16} />}
              className="mt-4 w-full sm:w-auto"
              onClick={() => setShowVisorModal(true)}
            >
              {t("view_visors")}
            </Button>
          )}
        </div>
        <ProductInfo product={displayProduct} shippingReturns={shippingReturns} />
      </div>

      <ProductTabs product={displayProduct} />
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

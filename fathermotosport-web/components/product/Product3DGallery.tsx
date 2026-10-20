"use client";

import { useState } from "react";
import dynamic from "next/dynamic";
import { useTranslations } from "next-intl";
import { Box } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/Skeleton";
import { MedioPrincipal, MiniaturaDeMedio, primeraImagen, useEtiquetaDeMiniatura } from "./MedioDeGaleria";

// El viewer 3D solo se carga en cliente (Three.js no debe renderizar en SSR).
const ProductViewer3D = dynamic(
  () => import("@/components/3d/ProductViewer3D").then((m) => m.ProductViewer3D),
  { ssr: false, loading: () => <Skeleton className="aspect-square w-full rounded-2xl" /> }
);

interface Product3DGalleryProps {
  modelUrl: string;
  images: ProductImage[];
  name: string;
  showVisorColors?: boolean;
}

/** Visor 3D primero y, en las miniaturas, la galería (imágenes, GIF y videos) en el orden de la API. */
export function Product3DGallery({ modelUrl, images, name, showVisorColors }: Product3DGalleryProps) {
  const t = useTranslations("gallery");
  const etiquetaDeMiniatura = useEtiquetaDeMiniatura();
  const [activeImage, setActiveImage] = useState<number | null>(null);
  const current = activeImage !== null ? images[activeImage] : null;

  return (
    <div className="space-y-3">
      {current ? (
        <div className="group relative aspect-square overflow-hidden rounded-2xl border border-white/10 bg-brand-dark">
          <MedioPrincipal
            key={current.id}
            medio={current}
            nombre={name}
            prioridad
            respaldoUrl={primeraImagen(images)}
            claseImagen="object-cover transition-transform duration-500 group-hover:scale-110"
          />
        </div>
      ) : (
        <ProductViewer3D url={modelUrl} showVisorColors={showVisorColors} />
      )}

      <div className="flex gap-2 overflow-x-auto">
        <button
          onClick={() => setActiveImage(null)}
          className={cn(
            "flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border-2 bg-brand-dark transition",
            activeImage === null ? "border-brand-red" : "border-white/10 hover:border-white/30"
          )}
          aria-label={t("view_3d")}
        >
          <Box size={24} className="text-brand-white" />
        </button>
        {images.map((img, i) => (
          <button
            key={img.id}
            onClick={() => setActiveImage(i)}
            aria-label={etiquetaDeMiniatura(img, i, images.length)}
            aria-current={activeImage === i || undefined}
            className={cn(
              "relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition",
              activeImage === i ? "border-brand-red" : "border-white/10 hover:border-white/30"
            )}
          >
            <MiniaturaDeMedio medio={img} />
          </button>
        ))}
      </div>
    </div>
  );
}

"use client";

import { useState } from "react";
import Script from "next/script";
import { useTranslations } from "next-intl";
import { RotateCw } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn } from "@/lib/utils";
import { MedioPrincipal, MiniaturaDeMedio, primeraImagen, useEtiquetaDeMiniatura } from "./MedioDeGaleria";

interface SpinGalleryProps {
  spinUrl: string;
  images: ProductImage[];
  name: string;
}

/** Spin 360° primero y, en las miniaturas, la galería (imágenes, GIF y videos) en el orden de la API. */
export function SpinGallery({ spinUrl, images, name }: SpinGalleryProps) {
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
        <>
          <div
            className="Sirv aspect-square w-full overflow-hidden rounded-2xl border border-white/10 bg-brand-dark"
            data-src={spinUrl}
            data-option-loop="true"
            data-option-autoplay="true"
            data-option-speed="50"
          />
          <Script
            src="https://scripts.sirv.com/sirvjs/v3/sirv.js"
            strategy="afterInteractive"
          />
        </>
      )}

      <div className="flex gap-2 overflow-x-auto">
        <button
          onClick={() => setActiveImage(null)}
          className={cn(
            "flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border-2 bg-brand-dark transition",
            activeImage === null ? "border-brand-red" : "border-white/10 hover:border-white/30"
          )}
          aria-label={t("view_spin")}
        >
          <RotateCw size={24} className="text-brand-white" />
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

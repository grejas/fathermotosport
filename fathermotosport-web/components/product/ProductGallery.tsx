"use client";

import { useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Box, ChevronLeft, ChevronRight } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn } from "@/lib/utils";
import { MedioPrincipal, MiniaturaDeMedio, primeraImagen, useEtiquetaDeMiniatura } from "./MedioDeGaleria";

// Distancia mínima de swipe (px) para contar como cambio de imagen y no un tap.
const SWIPE_THRESHOLD = 40;

/**
 * Galería de la ficha: imágenes, GIF y videos mezclados, en el orden en que llegan de
 * la API (el que se arrastra en el panel; un video puede ir primero).
 */
export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const t = useTranslations("gallery");
  const etiquetaDeMiniatura = useEtiquetaDeMiniatura();
  const [active, setActive] = useState(0);
  const current = images[active];
  const respaldo = primeraImagen(images);
  const touchStartX = useRef<number | null>(null);

  const goTo = (delta: number) => setActive((i) => (i + delta + images.length) % images.length);

  const onTouchStart = (e: React.TouchEvent) => {
    // Sobre los controles del video (barra de tiempo, botones) el deslizamiento es de
    // ellos, no un cambio de elemento.
    const enControl = (e.target as HTMLElement).closest("input, button");
    touchStartX.current = enControl ? null : e.touches[0].clientX;
  };

  const onTouchEnd = (e: React.TouchEvent) => {
    if (touchStartX.current === null) return;
    const deltaX = e.changedTouches[0].clientX - touchStartX.current;
    if (Math.abs(deltaX) > SWIPE_THRESHOLD) goTo(deltaX < 0 ? 1 : -1);
    touchStartX.current = null;
  };

  return (
    <div className="space-y-3">
      <div
        className="group relative aspect-square max-h-[400px] overflow-hidden rounded-2xl border border-white/10 bg-brand-dark sm:max-h-[500px] lg:max-h-none"
        onTouchStart={onTouchStart}
        onTouchEnd={onTouchEnd}
      >
        {current ? (
          <MedioPrincipal
            key={current.id}
            medio={current}
            nombre={name}
            prioridad={active === 0}
            respaldoUrl={respaldo}
            claseImagen="object-contain transition-transform duration-500 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center">
            <Box size={64} className="text-brand-muted opacity-30" />
          </div>
        )}

        {images.length > 1 && (
          <>
            <button
              onClick={() => goTo(-1)}
              className="absolute left-2 top-1/2 -translate-y-1/2 z-10 rounded-full bg-black/50 p-2 text-white backdrop-blur-sm transition hover:bg-black/70"
              aria-label={t("prev")}
            >
              <ChevronLeft size={20} />
            </button>
            <button
              onClick={() => goTo(1)}
              className="absolute right-2 top-1/2 -translate-y-1/2 z-10 rounded-full bg-black/50 p-2 text-white backdrop-blur-sm transition hover:bg-black/70"
              aria-label={t("next")}
            >
              <ChevronRight size={20} />
            </button>
          </>
        )}
      </div>

      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto">
          {images.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setActive(i)}
              aria-label={etiquetaDeMiniatura(img, i, images.length)}
              aria-current={i === active || undefined}
              className={cn(
                "relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white",
                i === active ? "border-brand-red" : "border-white/10 hover:border-white/30"
              )}
            >
              <MiniaturaDeMedio medio={img} />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

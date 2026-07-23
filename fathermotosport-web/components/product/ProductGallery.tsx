"use client";

import { useRef, useState } from "react";
import Image from "next/image";
import { Box, ChevronLeft, ChevronRight } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn, getImageUrl } from "@/lib/utils";

// Distancia mínima de swipe (px) para contar como cambio de imagen y no un tap.
const SWIPE_THRESHOLD = 40;

export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const sorted = [...images].sort(
    (a, b) => Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order
  );
  const [active, setActive] = useState(0);
  const current = sorted[active];
  const touchStartX = useRef<number | null>(null);

  const goTo = (delta: number) => setActive((i) => (i + delta + sorted.length) % sorted.length);

  const onTouchStart = (e: React.TouchEvent) => {
    touchStartX.current = e.touches[0].clientX;
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
        className="group relative aspect-square overflow-hidden rounded-2xl border border-white/10 bg-brand-dark"
        onTouchStart={onTouchStart}
        onTouchEnd={onTouchEnd}
      >
        {current ? (
          <Image
            src={getImageUrl(current.url)}
            alt={name}
            fill
            sizes="(max-width:1024px) 100vw, 50vw"
            className="object-cover transition-transform duration-500 group-hover:scale-110"
            priority
          />
        ) : (
          <div className="flex h-full items-center justify-center">
            <Box size={64} className="text-brand-muted opacity-30" />
          </div>
        )}

        {sorted.length > 1 && (
          <>
            <button
              onClick={() => goTo(-1)}
              className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2 text-brand-white backdrop-blur transition hover:bg-black/60"
              aria-label="Imagen anterior"
            >
              <ChevronLeft size={20} />
            </button>
            <button
              onClick={() => goTo(1)}
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2 text-brand-white backdrop-blur transition hover:bg-black/60"
              aria-label="Imagen siguiente"
            >
              <ChevronRight size={20} />
            </button>
          </>
        )}
      </div>

      {sorted.length > 1 && (
        <div className="flex gap-2 overflow-x-auto">
          {sorted.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setActive(i)}
              className={cn(
                "relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition",
                i === active ? "border-brand-red" : "border-white/10 hover:border-white/30"
              )}
            >
              <Image src={getImageUrl(img.thumbnail_url ?? img.url)} alt="" fill className="object-cover" sizes="64px" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

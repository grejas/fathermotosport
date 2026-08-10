"use client";

import { useState } from "react";
import Image from "next/image";
import { RotateCw } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn, getImageUrl } from "@/lib/utils";

interface SpinGalleryProps {
  spinUrl: string;
  images: ProductImage[];
  name: string;
}

export function SpinGallery({ spinUrl, images, name }: SpinGalleryProps) {
  const sorted = [...images].sort(
    (a, b) => Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order
  );
  const [activeImage, setActiveImage] = useState<number | null>(null);
  const current = activeImage !== null ? sorted[activeImage] : null;

  return (
    <div className="space-y-3">
      {current ? (
        <div className="group relative aspect-square overflow-hidden rounded-2xl border border-white/10 bg-brand-dark">
          <Image
            src={getImageUrl(current.url)}
            alt={name}
            fill
            sizes="(max-width:1024px) 100vw, 50vw"
            className="object-cover transition-transform duration-500 group-hover:scale-110"
            priority
          />
        </div>
      ) : (
        <iframe
          src={`${spinUrl}?image.type=webp&spin.speed=20&spin.loop=true&spin.autoplay=true`}
          width="100%"
          height="100%"
          frameBorder="0"
          allowFullScreen
          className="aspect-square w-full overflow-hidden rounded-2xl border border-white/10 bg-brand-dark"
        />
      )}

      <div className="flex gap-2 overflow-x-auto">
        <button
          onClick={() => setActiveImage(null)}
          className={cn(
            "flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border-2 bg-brand-dark transition",
            activeImage === null ? "border-brand-red" : "border-white/10 hover:border-white/30"
          )}
          aria-label="Ver spin 360°"
        >
          <RotateCw size={24} className="text-brand-white" />
        </button>
        {sorted.map((img, i) => (
          <button
            key={img.id}
            onClick={() => setActiveImage(i)}
            className={cn(
              "relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition",
              activeImage === i ? "border-brand-red" : "border-white/10 hover:border-white/30"
            )}
          >
            <Image src={getImageUrl(img.thumbnail_url ?? img.url)} alt="" fill className="object-cover" sizes="64px" />
          </button>
        ))}
      </div>
    </div>
  );
}

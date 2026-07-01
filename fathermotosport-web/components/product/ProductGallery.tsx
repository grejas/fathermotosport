"use client";

import { useState } from "react";
import Image from "next/image";
import { Box } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { cn, getImageUrl } from "@/lib/utils";

export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const sorted = [...images].sort(
    (a, b) => Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order
  );
  const [active, setActive] = useState(0);
  const current = sorted[active];

  return (
    <div className="space-y-3">
      <div className="group relative aspect-square overflow-hidden rounded-2xl border border-white/10 bg-brand-dark">
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

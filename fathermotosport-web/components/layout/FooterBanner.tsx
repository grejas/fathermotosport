"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { getBanners, type Banner } from "@/lib/api/banners";

/**
 * Sección promocional configurada desde el panel admin (banner position=footer).
 * Se muestra encima del footer del sitio. Si no hay banner footer activo, no
 * renderiza nada (comportamiento correcto).
 */
export function FooterBanner() {
  const [banner, setBanner] = useState<Banner | null>(null);

  useEffect(() => {
    getBanners("footer")
      .then((data) => setBanner(data[0] ?? null))
      .catch(() => {});
  }, []);

  if (!banner) return null;

  return (
    <section className="relative w-full overflow-hidden" style={{ minHeight: "220px" }}>
      {/* Imagen de fondo del banner. */}
      {banner.image_url && (
        <div
          className="absolute inset-0 bg-cover bg-center"
          style={{ backgroundImage: `url(${banner.image_url})`, opacity: 0.55 }}
        />
      )}
      {/* Overlay lateral: oscuro a la izquierda (texto legible), transparente a
          la derecha (imagen visible). */}
      <div className="absolute inset-0 bg-gradient-to-r from-brand-carbon/95 via-brand-carbon/60 to-transparent" />
      {/* Overlay inferior suave hacia el footer. */}
      <div className="absolute inset-0 bg-gradient-to-t from-brand-carbon/80 via-transparent to-transparent" />

      {/* Contenido centrado. */}
      <div className="relative z-10 flex flex-col items-center justify-center gap-6 px-6 py-16 text-center">
        {banner.title && (
          <h2 className="max-w-2xl text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">
            {banner.title}
          </h2>
        )}
        {banner.button_text && (
          <Link
            href={banner.link_url ?? "/catalog"}
            className="inline-flex h-12 items-center gap-2 rounded-full bg-brand-red px-8 text-sm font-medium uppercase tracking-widest text-white transition-all duration-[250ms] hover:opacity-85 hover:shadow-red-glow active:scale-[0.97]"
          >
            {banner.button_text}
          </Link>
        )}
      </div>
    </section>
  );
}

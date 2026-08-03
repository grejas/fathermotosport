"use client";

import { useEffect, useState } from "react";
import { useLocale } from "next-intl";
import Link from "next/link";
import { getBanners, type Banner } from "@/lib/api/banners";
import { translateText } from "@/lib/utils/translate";

/**
 * Sección promocional configurada desde el panel admin (banner position=footer).
 * Se muestra encima del footer del sitio. Si no hay banner footer activo, no
 * renderiza nada (comportamiento correcto).
 */
export function FooterBanner() {
  const locale = useLocale();
  const [banner, setBanner] = useState<Banner | null>(null);
  const [translated, setTranslated] = useState<{ title: string; button_text: string | null } | null>(null);

  useEffect(() => {
    getBanners("footer")
      .then((data) => setBanner(data[0] ?? null))
      .catch(() => {});
  }, []);

  // Traduce silenciosamente el contenido del banner (title/button_text, cargado
  // del admin en español) cuando el locale activo es pt/en.
  useEffect(() => {
    if (!banner || locale === "es") {
      setTranslated(null);
      return;
    }
    let cancelled = false;
    Promise.all([
      translateText(banner.title, locale),
      banner.button_text ? translateText(banner.button_text, locale) : Promise.resolve(banner.button_text),
    ]).then(([title, button_text]) => {
      if (!cancelled) setTranslated({ title, button_text });
    });
    return () => {
      cancelled = true;
    };
  }, [banner, locale]);

  if (!banner) return null;

  const displayTitle = translated?.title ?? banner.title;
  const displayButtonText = translated?.button_text ?? banner.button_text;

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
        {displayTitle && (
          <h2 className="max-w-2xl text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">
            {displayTitle}
          </h2>
        )}
        {displayButtonText && (
          <Link
            href={banner.link_url ?? "/catalog"}
            className="inline-flex h-12 items-center gap-2 rounded-full bg-brand-red px-8 text-sm font-medium uppercase tracking-widest text-white transition-all duration-[250ms] hover:opacity-85 hover:shadow-red-glow active:scale-[0.97]"
          >
            {displayButtonText}
          </Link>
        )}
      </div>
    </section>
  );
}

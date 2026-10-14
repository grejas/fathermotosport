"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { ArrowRight, ChevronLeft, ChevronRight } from "lucide-react";
import type { Banner } from "@/lib/api/banners";
import { useTextoTraducido } from "@/lib/hooks/useTextoTraducido";
import { BannerVideo } from "@/components/media/BannerVideo";
import { cn } from "@/lib/utils";
import { TextoConMarcas } from "@/components/ui/TextoConMarcas";

/** Hasta cuántos elementos se muestran en cuadrícula; desde el siguiente, carrusel. */
const MAX_EN_CUADRICULA = 3;

/**
 * Banners de la posición "Debajo de categorías", entre "Explora por categoría" y el
 * bloque de cuotas. 1 elemento: ancho completo. 2 o 3: cuadrícula. Más: carrusel.
 * Sin elementos activos no se dibuja nada (ni el espacio).
 */
export function HomeAfterCategories({ banners }: { banners: Banner[] }) {
  if (banners.length === 0) return null;

  return (
    <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
      {banners.length === 1 ? (
        <Elemento banner={banners[0]} ancho />
      ) : banners.length <= MAX_EN_CUADRICULA ? (
        <div className={cn("grid gap-5 sm:grid-cols-2", banners.length === 3 && "lg:grid-cols-3")}>
          {banners.map((b) => (
            <Elemento key={b.id} banner={b} />
          ))}
        </div>
      ) : (
        <Carrusel banners={banners} />
      )}
    </section>
  );
}

/**
 * Carrusel con scroll horizontal nativo (deslizar en celulares, rueda o trackpad en
 * escritorio) y flechas para teclado y mouse. Cada tarjeta es un snap point.
 */
function Carrusel({ banners }: { banners: Banner[] }) {
  const t = useTranslations("banners");
  const pistaRef = useRef<HTMLDivElement>(null);
  const [alInicio, setAlInicio] = useState(true);
  const [alFinal, setAlFinal] = useState(false);

  const medir = useCallback(() => {
    const el = pistaRef.current;
    if (!el) return;
    setAlInicio(el.scrollLeft <= 4);
    setAlFinal(el.scrollLeft + el.clientWidth >= el.scrollWidth - 4);
  }, []);

  useEffect(() => {
    medir();
    window.addEventListener("resize", medir);
    return () => window.removeEventListener("resize", medir);
  }, [medir]);

  const mover = (sentido: 1 | -1) => {
    const el = pistaRef.current;
    if (!el) return;
    const reducido = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    el.scrollBy({ left: sentido * el.clientWidth * 0.9, behavior: reducido ? "auto" : "smooth" });
  };

  return (
    <div role="region" aria-roledescription={t("carousel")} aria-label={t("section_label")} className="relative">
      <div
        ref={pistaRef}
        onScroll={medir}
        // scroll-px: el snap respeta el relleno lateral. Sin esto, el navegador corría la
        // pista 16px al cargar para alinear la primera tarjeta y "Anterior" no quedaba inactiva.
        className="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-5 overflow-x-auto scroll-smooth px-4 pb-2 [scrollbar-width:none] motion-reduce:scroll-auto sm:-mx-6 sm:scroll-px-6 sm:px-6 [&::-webkit-scrollbar]:hidden"
      >
        {banners.map((b, i) => (
          <div
            key={b.id}
            role="group"
            aria-roledescription={t("slide")}
            aria-label={t("slide_label", { current: i + 1, total: banners.length })}
            className="w-[85%] shrink-0 snap-start sm:w-[calc(50%-10px)] lg:w-[calc(33.333%-14px)]"
          >
            <Elemento banner={b} />
          </div>
        ))}
      </div>

      <div className="mt-4 flex justify-end gap-2">
        <FlechaCarrusel etiqueta={t("prev")} inactiva={alInicio} onClick={() => mover(-1)}>
          <ChevronLeft size={18} aria-hidden />
        </FlechaCarrusel>
        <FlechaCarrusel etiqueta={t("next")} inactiva={alFinal} onClick={() => mover(1)}>
          <ChevronRight size={18} aria-hidden />
        </FlechaCarrusel>
      </div>
    </div>
  );
}

function FlechaCarrusel({
  etiqueta,
  inactiva,
  onClick,
  children,
}: {
  etiqueta: string;
  inactiva: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      aria-label={etiqueta}
      // aria-disabled y no disabled: un botón deshabilitado pierde el foco en el extremo.
      aria-disabled={inactiva}
      onClick={inactiva ? undefined : onClick}
      className={cn(
        "grid h-10 w-10 place-items-center rounded-full border border-white/15 bg-white/[0.04] text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white",
        inactiva ? "cursor-default opacity-35" : "hover:border-white/30 hover:bg-white/10"
      )}
    >
      {children}
    </button>
  );
}

/**
 * Una tarjeta: imagen o video en 16:9 y, debajo, título, texto y enlace opcionales.
 * Con `ancho` (un solo elemento) el texto va al costado del medio en escritorio.
 */
function Elemento({ banner, ancho = false }: { banner: Banner; ancho?: boolean }) {
  const t = useTranslations("banners");
  const locale = useLocale();
  const tituloId = useId();
  const titulo = useTextoTraducido(banner.title, locale);
  const texto = useTextoTraducido(banner.subtitle, locale);
  const boton = useTextoTraducido(banner.button_text, locale);
  const esVideo = banner.media_type === "video" && banner.video_url;
  const sizes = ancho ? "(min-width: 1024px) 60vw, 100vw" : "(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 85vw";

  return (
    <article
      aria-labelledby={titulo ? tituloId : undefined}
      className={cn(
        "flex h-full flex-col overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-brand-dark to-brand-card",
        ancho && "lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-center"
      )}
    >
      {esVideo ? (
        <BannerVideo
          videoUrl={banner.video_url!}
          videoMobileUrl={banner.video_mobile_url}
          posterUrl={banner.poster_url}
          conSonido={banner.video_with_sound}
          etiqueta={titulo ?? banner.title ?? t("section_label")}
          modo="seccion"
          sizes={sizes}
          className="rounded-none ring-0"
        />
      ) : (
        banner.image_url && (
          <div className="relative aspect-video w-full overflow-hidden bg-black">
            <Image src={banner.image_url} alt={titulo ?? banner.title ?? ""} fill sizes={sizes} className="object-cover" />
          </div>
        )
      )}

      {(titulo || texto || banner.link_url) && (
        <div className={cn("flex flex-1 flex-col gap-2 p-5", ancho && "lg:p-8")}>
          {titulo && (
            <h3 id={tituloId} className={cn("font-bold leading-snug text-white", ancho ? "text-2xl lg:text-3xl" : "text-lg")}>
              <TextoConMarcas texto={titulo} />
            </h3>
          )}
          {texto && (
            <p className="text-sm leading-relaxed text-brand-muted">
              <TextoConMarcas texto={texto} />
            </p>
          )}
          {banner.link_url && (
            <a
              href={banner.link_url}
              // Rojo claro y no el de la marca: sobre la tarjeta oscura el #E8001D da ~3,3:1
              // y no llega a AA para texto chico; este da ~5,8:1.
              className="mt-auto inline-flex w-fit items-center gap-2 pt-2 text-sm font-semibold uppercase tracking-wider text-[#ff5a6e] transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
            >
              {boton ? <TextoConMarcas texto={boton} /> : t("see_more")}
              <ArrowRight size={16} aria-hidden />
            </a>
          )}
        </div>
      )}
    </article>
  );
}

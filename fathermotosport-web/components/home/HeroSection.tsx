"use client";

import { useEffect, useRef } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Box, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { initHeroScroll } from "@/animations/scroll";
import type { Banner } from "@/lib/api/banners";
import { useTextoTraducido } from "@/lib/hooks/useTextoTraducido";
import { BannerVideo } from "@/components/media/BannerVideo";
import { cn } from "@/lib/utils";
import { HeroParticles } from "./HeroParticles";

/**
 * Hero de la portada. El banner "hero" llega del servidor (page.tsx), así el poster de
 * un video se precarga con prioridad: es lo primero que se pinta.
 *
 * Los textos, los botones y las estadísticas son siempre los fijos del hero: del banner
 * solo se usan el medio (imagen o video) y su botón opcional. El título y el texto del
 * banner son para "Debajo de categorías".
 *
 * Con video, el video ocupa el lugar de la imagen: en escritorio a la derecha del texto;
 * en celulares y tablets, entre la descripción y los botones. Siempre con sus controles.
 */
export function HeroSection({ banner }: { banner: Banner | null }) {
  const t = useTranslations("home");
  const locale = useLocale();
  const root = useRef<HTMLDivElement>(null);

  // Contenido del admin, cargado en español: se traduce al vuelo en pt/en.
  const botonBanner = useTextoTraducido(banner?.button_text, locale);

  const stats = [
    { value: "200+", label: t("products") },
    { value: "15+", label: t("brands") },
    { value: "5-7", label: t("days") },
  ];

  // Animación de entrada del hero (texto + botones + stats).
  useEffect(() => initHeroScroll(root.current), []);

  // El poster es opcional: sin él, BannerVideo muestra fondo oscuro y el primer fotograma.
  const video = banner?.media_type === "video" && banner.video_url ? banner : null;
  const imagen = !video ? banner?.image_url : null;

  return (
    <section
      ref={root}
      // El hero mide lo que mide su contenido (sin min-h) y se ancla arriba con
      // items-start. El combo min-h-[84vh] + items-center que había antes era lo que
      // dejaba el hueco muerto encima de "Colección 2026", con y sin promo.
      // El espacio se controla solo con padding, en dos escenarios por breakpoint:
      //   · pt-6  / md:pt-10       => CON promo: separación del banner de arriba.
      //   · first:pt-24 / md:first:pt-32 => SIN promo el hero es :first-child y tiene que
      //     despejar él mismo el navbar fijo (h-16 = 64px); el resto es aire.
      //   · pb-16 / md:pb-24       => separación con el marquee de abajo.
      className="relative flex items-start overflow-hidden pt-6 pb-16 first:pt-24 md:pt-10 md:pb-24 md:first:pt-32"
    >
      {/* Fondo: imagen del banner del admin, detrás de partículas y contenido.
          La imagen es cuadrada (874x874); se limita al 55% del ancho y se alinea
          a la derecha para que el casco no invada el texto de la izquierda. */}
      {imagen && (
        <div className="absolute inset-0">
          <div
            className="absolute inset-0"
            style={{
              backgroundImage: `url(${imagen})`,
              backgroundSize: "55%",
              backgroundPosition: "right center",
              backgroundRepeat: "no-repeat",
              opacity: 0.65,
            }}
          />
          {/* Scrim lateral: texto legible a la izquierda, banner visible a la derecha. */}
          <div className="absolute inset-0 bg-gradient-to-r from-brand-carbon via-brand-carbon/85 to-transparent" />
          {/* Scrim inferior. */}
          <div className="absolute inset-0 bg-gradient-to-t from-brand-carbon/60 via-transparent to-transparent" />
        </div>
      )}
      <HeroParticles />
      {/* Sin imagen (sin banner o con video): el gradiente original. */}
      {!imagen && (
        <div className="absolute inset-0 bg-gradient-to-t from-brand-carbon via-brand-carbon/40 to-transparent" />
      )}

      <div className="relative mx-auto w-full max-w-7xl px-4 sm:px-6">
        {/* Con video, la columna no es "relative" en escritorio: así el video (que va
            dentro, entre el texto y los botones) se posiciona respecto del contenedor. */}
        <div className={cn("relative max-w-2xl", video && "lg:static lg:max-w-[44%]")}>
          <span
            data-hero-tag
            className="inline-flex items-center gap-2 rounded-full border border-brand-red/30 bg-brand-red/10 px-3 py-1 text-xs font-semibold text-brand-red"
          >
            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-red" />
            {t("collection_tag")}
          </span>

          <h1 data-hero-title className="mt-5 text-5xl font-extrabold leading-[1.05] text-brand-white sm:text-6xl">
            {t("hero_title")} <span className="text-brand-red">{t("hero_highlight")}</span>
          </h1>

          <p data-hero-sub className="mt-5 max-w-lg text-lg text-brand-muted">
            {t("hero_desc")}
          </p>

          {video && (
            // En celulares y tablets, entre la descripción y los botones: va en ese lugar
            // del DOM (no con CSS order), así el orden de lectura y de tabulación es el
            // visual. 4:3 en celular y 25:16 en tablet (la columna mide 672px: 430px de
            // alto), nunca más del 42% del alto de la pantalla. La proporción la reserva el CSS: no hay salto de layout.
            // En escritorio, el lugar de la imagen: a la derecha del texto (que se
            // angosta para no taparlo), centrado en alto.
            <div className="relative mt-8 lg:absolute lg:right-6 lg:top-1/2 lg:mt-0 lg:w-[52%] lg:-translate-y-1/2">
              <BannerVideo
                videoUrl={video.video_url!}
                videoMobileUrl={video.video_mobile_url}
                posterUrl={video.poster_url}
                conSonido={video.video_with_sound}
                etiqueta={`${t("hero_title")} ${t("hero_highlight")}`}
                modo="hero"
                prioridad
                sizes="(min-width: 1024px) 52vw, 100vw"
                proporcion="aspect-[4/3] max-h-[42svh] md:aspect-[25/16] lg:aspect-video lg:max-h-none"
                className="shadow-[0_24px_64px_rgba(0,0,0,0.5)]"
              />
            </div>
          )}

          <div className="mt-8 flex flex-wrap gap-3">
            <Link href="/catalog" data-hero-cta>
              <Button variant="primary" size="lg" icon={<ArrowRight size={18} />}>
                {t("view_catalog")}
              </Button>
            </Link>
            <Link href="/catalog" data-hero-cta>
              <Button variant="glass" size="lg" icon={<Box size={18} />}>
                {t("view_3d")}
              </Button>
            </Link>

            {/* Botón configurado en el admin (button_text), con el mismo estilo
                glass/pill que "Ver en 3D". Si no hay link_url, cae a /catalog.

                La animación de entrada (GSAP, data-hero-cta) va en un envoltorio sin
                transición CSS: con transition-all en el mismo elemento, GSAP lo dejaba en
                opacity 0. Ahora que el banner llega con la página, la animación lo alcanza. */}
            {banner?.button_text && (
              <span data-hero-cta className="inline-flex">
                <a
                  href={banner.link_url ?? "/catalog"}
                  className="inline-flex h-11 items-center gap-2 rounded-full border border-white/20 bg-white/[0.08] px-6 text-sm font-medium uppercase tracking-widest text-white backdrop-blur-sm transition-all duration-[250ms] hover:border-white/35 hover:bg-white/[0.14]"
                >
                  {botonBanner}
                </a>
              </span>
            )}
          </div>

          <div className="mt-12 flex gap-10">
            {stats.map((s) => (
              <div key={s.label} data-hero-stat>
                <p className="text-3xl font-extrabold text-brand-white">{s.value}</p>
                <p className="text-sm text-brand-muted">{s.label}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* La insignia 3D va en la esquina de la imagen; con video estaría sobre sus controles. */}
      {!video && (
        <div className="absolute bottom-8 right-8 hidden items-center gap-2 rounded-full bg-black/50 px-4 py-2 text-sm font-bold text-brand-white backdrop-blur lg:flex">
          <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-brand-red" /> 360° 3D
        </div>
      )}
    </section>
  );
}

"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Box, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { initHeroScroll } from "@/animations/scroll";
import { getBanners, type Banner } from "@/lib/api/banners";
import { HeroParticles } from "./HeroParticles";

export function HeroSection() {
  const t = useTranslations("home");
  const root = useRef<HTMLDivElement>(null);
  const [heroBanner, setHeroBanner] = useState<Banner | null>(null);

  const stats = [
    { value: "200+", label: t("products") },
    { value: "15+", label: t("brands") },
    { value: "5-7", label: t("days") },
  ];

  // Animación de entrada del hero (texto + botones + stats).
  useEffect(() => initHeroScroll(root.current), []);

  // Banner "hero" configurado desde el panel admin (si existe, se usa como fondo).
  useEffect(() => {
    getBanners("hero")
      .then((banners) => setHeroBanner(banners[0] ?? null))
      .catch(() => {});
  }, []);

  return (
    <section ref={root} className="relative flex min-h-[92vh] items-center overflow-hidden">
      {/* Fondo: imagen del banner del admin, detrás de partículas y contenido.
          La imagen es cuadrada (874x874); se limita al 55% del ancho y se alinea
          a la derecha para que el casco no invada el texto de la izquierda. */}
      {heroBanner?.image_url && (
        <div className="absolute inset-0">
          <div
            className="absolute inset-0"
            style={{
              backgroundImage: `url(${heroBanner.image_url})`,
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
      {/* Sin banner: se mantiene el gradiente original (hero idéntico al de antes). */}
      {!heroBanner?.image_url && (
        <div className="absolute inset-0 bg-gradient-to-t from-brand-carbon via-brand-carbon/40 to-transparent" />
      )}

      <div className="relative mx-auto w-full max-w-7xl px-4 sm:px-6">
        <div className="max-w-2xl">
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
                glass/pill que "Ver en 3D". Si no hay link_url, cae a /catalog. */}
            {heroBanner?.button_text && (
              <a
                href={heroBanner.link_url ?? "/catalog"}
                data-hero-cta
                className="inline-flex h-11 items-center gap-2 rounded-full border border-white/20 bg-white/[0.08] px-6 text-sm font-medium uppercase tracking-widest text-white backdrop-blur-sm transition-all duration-[250ms] hover:border-white/35 hover:bg-white/[0.14]"
              >
                {heroBanner.button_text}
              </a>
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

      <div className="absolute bottom-8 right-8 hidden items-center gap-2 rounded-full bg-black/50 px-4 py-2 text-sm font-bold text-brand-white backdrop-blur lg:flex">
        <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-brand-red" /> 360° 3D
      </div>
    </section>
  );
}

"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import { Box, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { initHeroScroll } from "@/animations/scroll";
import { HeroParticles } from "./HeroParticles";

const stats = [
  { value: "200+", label: "Productos" },
  { value: "15+", label: "Marcas" },
  { value: "5-7", label: "Dias habiles" },
];

export function HeroSection() {
  const root = useRef<HTMLDivElement>(null);

  useEffect(() => initHeroScroll(root.current), []);

  return (
    <section ref={root} className="relative flex min-h-[92vh] items-center overflow-hidden">
      <HeroParticles />
      <div className="absolute inset-0 bg-gradient-to-t from-brand-carbon via-brand-carbon/40 to-transparent" />

      <div className="relative mx-auto w-full max-w-7xl px-4 sm:px-6">
        <div className="max-w-2xl">
          <span
            data-hero-tag
            className="inline-flex items-center gap-2 rounded-full border border-brand-red/30 bg-brand-red/10 px-3 py-1 text-xs font-semibold text-brand-red"
          >
            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-red" />
            Colección 2026 · Disponible ahora
          </span>

          <h1 data-hero-title className="mt-5 text-5xl font-extrabold leading-[1.05] text-brand-white sm:text-6xl">
            Equípate como un <span className="text-brand-red">campeón</span> de pista
          </h1>

          <p data-hero-sub className="mt-5 max-w-lg text-lg text-brand-muted">
            Cascos, guantes y protección premium con certificación internacional. Envío gratis en Bolivia
            y Brasil, y vista previa en 3D real.
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <Link href="/catalog" data-hero-cta>
              <Button variant="primary" size="lg" icon={<ArrowRight size={18} />}>
                Ver catálogo
              </Button>
            </Link>
            <Link href="/catalog" data-hero-cta>
              <Button variant="glass" size="lg" icon={<Box size={18} />}>
                Ver en 3D
              </Button>
            </Link>
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

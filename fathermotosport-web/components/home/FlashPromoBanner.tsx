"use client";

import { useEffect, useState } from "react";
import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { Zap } from "lucide-react";
import type { FlashPromo } from "@/lib/api/flashPromo";
import { useFlashPromos } from "@/lib/hooks/useFlashPromo";
import { useCountdown } from "@/lib/hooks/useCountdown";
import { useMounted } from "@/lib/hooks/useMounted";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

// Cada cuánto rota el carrusel cuando hay más de una promo activa (ms).
const ROTATE_MS = 4500;

// Palabras clave que se resaltan en dorado dentro del texto libre de la promo.
const HIGHLIGHT_WORDS = "gratis|gratuit[oa]s?|regalo|descuento|dscto|off|2x1|3x2|\\d+%";
// `split` con grupo de captura conserva los delimitadores en el resultado.
const HIGHLIGHT_SPLIT = new RegExp(`(${HIGHLIGHT_WORDS})`, "gi");
// Test anclado y SIN flag global (evita el estado mutable de lastIndex).
const HIGHLIGHT_TEST = new RegExp(`^(${HIGHLIGHT_WORDS})$`, "i");

// Esquinas cortadas angulares (superior-derecha e inferior-izquierda).
const CLIP_PATH =
  "polygon(0 0, calc(100% - 22px) 0, 100% 22px, 100% 100%, 22px 100%, 0 calc(100% - 22px))";

/** Envuelve las palabras "gancho" en dorado para dar contraste. */
function renderPromoText(text: string) {
  return text.split(HIGHLIGHT_SPLIT).map((part, i) =>
    HIGHLIGHT_TEST.test(part) ? (
      <span key={i} className="text-brand-gold" style={{ textShadow: "0 0 14px rgba(201,168,76,0.65)" }}>
        {part}
      </span>
    ) : (
      <span key={i}>{part}</span>
    )
  );
}

interface Dots {
  count: number;
  active: number;
  onSelect: (index: number) => void;
}

/** Un panel de promo con su propio contador regresivo real. */
function PromoSlide({ promo, onExpire, dots }: { promo: FlashPromo; onExpire: () => void; dots?: Dots }) {
  const t = useTranslations("flash_promo");
  const { remaining, segments } = useCountdown(promo.ends_at, true);

  useEffect(() => {
    if (remaining <= 0) onExpire();
  }, [remaining, onExpire]);

  if (remaining <= 0) return null;

  return (
    <div className="animate-fade-in" style={{ filter: "drop-shadow(0 0 22px rgba(232,0,29,0.28))" }}>
      {/* Capa de borde: dorado fino y tenue que recorre TODO el contorno. */}
      <div className="p-px" style={{ clipPath: CLIP_PATH, background: "rgba(201,168,76,0.30)" }}>
        <div
          className="relative overflow-hidden px-4 pb-4 pt-3 md:px-8 md:pb-5 md:pt-3.5"
          style={{
            background:
              "radial-gradient(circle at 14% 22%, rgba(232,0,29,0.22) 0%, transparent 46%), " +
              "radial-gradient(circle at 88% 82%, rgba(232,0,29,0.12) 0%, transparent 52%), " +
              "radial-gradient(rgba(255,255,255,0.035) 1px, transparent 1px), " +
              "#0A0A0A",
            backgroundSize: "100% 100%, 100% 100%, 18px 18px, 100% 100%",
            clipPath: CLIP_PATH,
          }}
        >
          {/* Barrido de brillo diagonal animado */}
          <span
            aria-hidden
            className="pointer-events-none absolute inset-0 animate-flash-shine"
            style={{
              background:
                "linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.14) 50%, transparent 60%)",
            }}
          />

          {/* Una sola fila en mobile (ícono + texto + contador en línea, como el banner
              compacto de producto). En md: crece al layout grande con justify-between. */}
          <div className="relative flex items-center justify-between gap-3 md:gap-4">
            {/* Grupo ícono + texto: en mobile toma el espacio disponible y el texto trunca;
                en desktop se ajusta al contenido. */}
            <div className="flex min-w-0 flex-1 items-center gap-2.5 md:flex-initial md:gap-4">
              <span
                aria-hidden
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-black/30 ring-2 ring-brand-gold/60 animate-flash-glow md:h-14 md:w-14"
              >
                <Zap className="h-4 w-4 text-brand-gold md:h-7 md:w-7" fill="#C9A84C" />
              </span>
              <div className="min-w-0">
                <span className="inline-block rounded-sm bg-brand-gold px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.18em] text-brand-carbon md:px-2.5 md:text-[11px]">
                  {t("badge")}
                </span>
                <p
                  className={`${bebasNeue.className} mt-0.5 truncate text-base uppercase leading-none tracking-wide text-white md:mt-1 md:overflow-visible md:whitespace-nowrap md:text-2xl`}
                  style={{ textShadow: "0 2px 10px rgba(0,0,0,0.7)" }}
                >
                  {renderPromoText(promo.promo_text!)}
                </p>
              </div>
            </div>

            <div className="flex shrink-0 flex-col items-center gap-1 md:gap-1.5">
              <span className="hidden text-[9px] font-bold uppercase tracking-[0.2em] text-white/85 md:block md:text-[11px]">
                {t("ends_in")}
              </span>
              <div className="flex items-end gap-1 md:gap-2">
                {segments.map((seg, i) => (
                  <div key={`${seg.unitKey}-${i}`} className="flex flex-col items-center">
                    <span
                      className={`${bebasNeue.className} min-w-[1.75rem] rounded border border-brand-gold/20 bg-black/45 px-1 py-0.5 text-center text-lg leading-none tabular-nums text-brand-gold md:min-w-[3.25rem] md:rounded-md md:px-2 md:py-1 md:text-4xl`}
                      style={{ textShadow: "0 0 12px rgba(201,168,76,0.7)" }}
                    >
                      {String(seg.value).padStart(2, "0")}
                    </span>
                    <span className="mt-0.5 text-[8px] font-bold uppercase tracking-widest text-white/70 md:mt-1 md:text-[10px]">
                      {t(seg.unitKey)}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Dots del carrusel DENTRO del panel (overlay): no agregan altura externa,
              así el alto del banner es idéntico con 1 o varias promos. */}
          {dots && dots.count > 1 && (
            <div className="absolute inset-x-0 bottom-1.5 z-10 flex justify-center gap-2">
              {Array.from({ length: dots.count }).map((_, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => dots.onSelect(i)}
                  aria-label={`Promoción ${i + 1} de ${dots.count}`}
                  className={`h-1.5 rounded-full transition-all ${
                    i === dots.active ? "w-5 bg-brand-gold" : "w-1.5 bg-white/40 hover:bg-white/60"
                  }`}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export function FlashPromoBanner() {
  const mounted = useMounted();
  const { data, refetch } = useFlashPromos();

  const promos = (data ?? []).filter((p) => p.promo_text && p.ends_at);
  const [index, setIndex] = useState(0);

  // Carrusel: rota solo si hay más de una promo activa.
  useEffect(() => {
    if (promos.length <= 1) return;
    const id = setInterval(() => setIndex((i) => (i + 1) % promos.length), ROTATE_MS);
    return () => clearInterval(id);
  }, [promos.length]);

  // Mantener el índice en rango si la lista cambia (ej. una promo expira).
  useEffect(() => {
    if (promos.length > 0 && index >= promos.length) setIndex(0);
  }, [promos.length, index]);

  if (!mounted || promos.length === 0) return null;

  const safeIndex = index % promos.length;
  const current = promos[safeIndex];

  return (
    <section className="mx-auto max-w-7xl px-4 pb-3 pt-20 sm:px-6">
      {/* pt-20 despeja el navbar fijo (h-16). pb-3 = separación mínima y constante con el hero.
          Los dots van DENTRO del panel (overlay), así el alto no cambia según la cantidad de promos. */}
      {/* key por id => transición fade al rotar de promo. */}
      <PromoSlide
        key={current.id}
        promo={current}
        onExpire={refetch}
        dots={{ count: promos.length, active: safeIndex, onSelect: setIndex }}
      />
    </section>
  );
}

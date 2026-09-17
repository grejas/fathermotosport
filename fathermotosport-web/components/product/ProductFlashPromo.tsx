"use client";

import { useEffect } from "react";
import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { Zap } from "lucide-react";
import { useFlashPromos } from "@/lib/hooks/useFlashPromo";
import { useCountdown } from "@/lib/hooks/useCountdown";
import { pickPromoForCategory } from "@/lib/api/flashPromo";
import { useMounted } from "@/lib/hooks/useMounted";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

interface Props {
  categoryId?: number | string | null;
  categorySlug?: string | null;
}

/**
 * Banner compacto de promo flash para la página de producto. Comparte EXACTAMENTE
 * la estética del banner de la home (fondo carbon con halo rojo sutil + textura de
 * puntos, borde dorado fino recorriendo todo el contorno con esquinas cortadas),
 * en una sola fila. Su contador se deriva del mismo ends_at vía useCountdown.
 */
export function ProductFlashPromo({ categoryId, categorySlug }: Props) {
  const t = useTranslations("flash_promo");
  const mounted = useMounted();
  const { data, refetch } = useFlashPromos();

  // De las promos activas que aplican a esta categoría, prioriza la específica
  // de categoría sobre la de toda la tienda.
  const promo = pickPromoForCategory(data ?? [], categoryId, categorySlug);
  const { remaining, segments } = useCountdown(promo?.ends_at, !!promo);

  useEffect(() => {
    if (promo && remaining <= 0) refetch();
  }, [promo, remaining, refetch]);

  if (!mounted || !promo || remaining <= 0) return null;

  // Esquinas cortadas angulares (más pequeñas que en la home, por ser compacto).
  const clipPath =
    "polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 14px 100%, 0 calc(100% - 14px))";

  return (
    <div className="mb-6" style={{ filter: "drop-shadow(0 0 16px rgba(232,0,29,0.28))" }}>
      {/* Capa de borde: dorado fino y tenue por todo el contorno (incl. chaflanes). */}
      <div className="p-px" style={{ clipPath, background: "rgba(201,168,76,0.30)" }}>
        <div
          className="relative flex flex-wrap items-center gap-x-4 gap-y-2 overflow-hidden px-4 py-3"
          style={{
            // Fondo oscuro carbon con SOLO un halo rojo sutil y textura de puntos leve.
            background:
              "radial-gradient(circle at 12% 30%, rgba(232,0,29,0.22) 0%, transparent 50%), " +
              "radial-gradient(circle at 90% 80%, rgba(232,0,29,0.12) 0%, transparent 55%), " +
              "radial-gradient(rgba(255,255,255,0.035) 1px, transparent 1px), " +
              "#0A0A0A",
            backgroundSize: "100% 100%, 100% 100%, 18px 18px, 100% 100%",
            clipPath,
          }}
        >
          {/* Barrido de brillo diagonal animado */}
          <span
            aria-hidden
            className="pointer-events-none absolute inset-0 animate-flash-shine"
            style={{
              background:
                "linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.12) 50%, transparent 60%)",
            }}
          />

          <span
            aria-hidden
            className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-black/30 ring-2 ring-brand-gold/60 animate-flash-glow"
          >
            <Zap size={18} className="text-brand-gold" fill="#C9A84C" />
          </span>

          <div className="relative min-w-0 flex-1">
            <span className="text-[10px] font-black uppercase tracking-[0.18em] text-brand-gold">
              {t("badge")}
            </span>
            <p
              className={`${bebasNeue.className} text-lg uppercase leading-tight tracking-wide text-white sm:text-xl`}
              style={{ textShadow: "0 1px 6px rgba(0,0,0,0.7)" }}
            >
              {promo.promo_text}
            </p>
          </div>

          <div className="relative flex shrink-0 items-center gap-2">
            <span className="hidden text-[10px] font-bold uppercase tracking-widest text-white/80 sm:inline">
              {t("ends_in")}
            </span>
            <div className="flex items-end gap-1">
              {segments.map((seg, i) => (
                <div key={`${seg.unitKey}-${i}`} className="flex flex-col items-center">
                  <span
                    className={`${bebasNeue.className} min-w-[2.25rem] rounded border border-brand-gold/20 bg-black/45 px-1.5 py-0.5 text-center text-xl leading-none tabular-nums text-brand-gold`}
                    style={{ textShadow: "0 0 10px rgba(201,168,76,0.7)" }}
                  >
                    {String(seg.value).padStart(2, "0")}
                  </span>
                  <span className="mt-0.5 text-[8px] font-bold uppercase tracking-wide text-white/70">
                    {t(seg.unitKey)}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

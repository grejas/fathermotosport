"use client";

import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { ArrowRight, Flame, Hand, Footprints, Package, Shirt, type LucideIcon } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { useCountdown } from "@/lib/hooks/useCountdown";
import { useMounted } from "@/lib/hooks/useMounted";

const bebas = Bebas_Neue({ weight: "400", subsets: ["latin"] });

/** Fin de la promo: 11/11/2026 23:59 hora de Bolivia (UTC-4). Después el bloque desaparece solo. */
const ENDS_AT = "2026-11-11T23:59:00-04:00";

interface Tile {
  key: "gloves" | "jackets" | "boots" | "waist";
  href: string;
  Icon: LucideIcon;
}

// Categorías con el 10% realmente aplicado en el panel (Guantes 2, Chaquetas 5, Botas 3)
// y las riñoneras de Accesorios (7), que llevan el precio de oferta por producto.
const TILES: Tile[] = [
  { key: "gloves", href: "/catalog?category=2", Icon: Hand },
  { key: "jackets", href: "/catalog?category=5", Icon: Shirt },
  { key: "boots", href: "/catalog?category=3", Icon: Footprints },
  { key: "waist", href: "/catalog?category=7&search=Ri%C3%B1onera", Icon: Package },
];

const CLIP = "polygon(0 0, calc(100% - 28px) 0, 100% 28px, 100% 100%, 28px 100%, 0 calc(100% - 28px))";

/**
 * Evento 11.11 LIQUIDACIÓN de la home: numeral gigante en degradado dorado, cinta roja
 * inclinada, cuenta regresiva real y accesos a las categorías en oferta. Solo promete lo
 * que está aplicado (10% en las categorías y riñoneras listadas) y se oculta al terminar.
 */
export function Promo1111() {
  const t = useTranslations("promo_1111");
  const tFlash = useTranslations("flash_promo");
  const mounted = useMounted();
  const { remaining, segments } = useCountdown(ENDS_AT, true);

  if (!mounted || remaining <= 0) return null;

  return (
    <section aria-label={t("aria")} className="mx-auto max-w-7xl px-4 pt-6 sm:px-6">
      <div style={{ filter: "drop-shadow(0 0 28px rgba(232,0,29,0.35))" }}>
        <div className="p-px" style={{ clipPath: CLIP, background: "linear-gradient(135deg, #E8C766, rgba(201,168,76,0.25) 40%, #E8001D)" }}>
          <div
            className="relative overflow-hidden px-5 py-8 sm:px-10 sm:py-10"
            style={{
              clipPath: CLIP,
              background:
                "radial-gradient(circle at 12% 20%, rgba(232,0,29,0.38) 0%, transparent 48%), " +
                "radial-gradient(circle at 92% 90%, rgba(201,168,76,0.18) 0%, transparent 50%), " +
                "repeating-linear-gradient(115deg, rgba(255,255,255,0.025) 0 2px, transparent 2px 14px), " +
                "#0A0A0A",
            }}
          >
            <span
              aria-hidden
              className="pointer-events-none absolute inset-0 animate-flash-shine"
              style={{ background: "linear-gradient(105deg, transparent 42%, rgba(255,255,255,0.10) 50%, transparent 58%)" }}
            />

            <div className="relative grid items-center gap-8 lg:grid-cols-[1.1fr_1fr]">
              {/* Marca del evento */}
              <div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-gold/40 bg-black/40 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-brand-gold">
                  <Flame size={12} className="text-brand-red" fill="#E8001D" />
                  {t("eyebrow")}
                </span>

                <h2 className="mt-3 flex flex-wrap items-end gap-x-4 leading-none">
                  <span
                    className={`${bebas.className} text-[5.5rem] tracking-wide sm:text-[8rem]`}
                    style={{
                      backgroundImage: "linear-gradient(180deg, #FFF3C4 0%, #E8C766 45%, #A8832B 100%)",
                      WebkitBackgroundClip: "text",
                      backgroundClip: "text",
                      color: "transparent",
                      filter: "drop-shadow(0 4px 18px rgba(201,168,76,0.35))",
                    }}
                  >
                    11.11
                  </span>
                  <span
                    className={`${bebas.className} mb-2 inline-block -skew-x-12 bg-brand-red px-4 py-1 text-3xl uppercase tracking-wider text-white shadow-red-glow sm:mb-4 sm:text-5xl`}
                  >
                    <span className="inline-block skew-x-12">{t("title")}</span>
                  </span>
                </h2>

                <p className="mt-4 max-w-md text-sm text-white/80 sm:text-base">
                  <span className="font-extrabold text-brand-gold">{t("off")}</span> {t("subtitle")}
                </p>

                {/* Cuenta regresiva real hasta el fin de la promo */}
                <div className="mt-5">
                  <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">{t("ends_in")}</p>
                  <div className="flex items-end gap-2">
                    {segments.map((seg, i) => (
                      <div key={`${seg.unitKey}-${i}`} className="flex flex-col items-center">
                        <span
                          className={`${bebas.className} min-w-[3.25rem] rounded-md border border-brand-gold/30 bg-black/55 px-2 py-1 text-center text-3xl leading-none tabular-nums text-brand-gold sm:min-w-[4rem] sm:text-4xl`}
                          style={{ textShadow: "0 0 12px rgba(201,168,76,0.6)" }}
                        >
                          {String(seg.value).padStart(2, "0")}
                        </span>
                        <span className="mt-1 text-[9px] font-bold uppercase tracking-wide text-white/70">
                          {tFlash(seg.unitKey)}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Categorías en oferta */}
              <div>
                <div className="grid grid-cols-2 gap-3">
                  {TILES.map(({ key, href, Icon }) => (
                    <Link
                      key={key}
                      href={href}
                      className="group relative flex flex-col gap-3 overflow-hidden rounded-xl border border-white/10 bg-white/[0.04] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-brand-gold/50 hover:bg-white/[0.08]"
                    >
                      <span className={`${bebas.className} absolute right-0 top-0 rounded-bl-xl bg-brand-red px-2.5 py-1 text-lg leading-none text-white`}>
                        -10%
                      </span>
                      <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-gold/10 text-brand-gold ring-1 ring-brand-gold/30">
                        <Icon size={22} />
                      </span>
                      <span className="text-sm font-bold text-brand-white">{t(`tiles.${key}`)}</span>
                      <span className="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-brand-gold transition-all group-hover:gap-2">
                        {t("shop")} <ArrowRight size={12} />
                      </span>
                    </Link>
                  ))}
                </div>
                <p className="mt-3 text-center text-[11px] text-white/50">{t("fine_print")}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

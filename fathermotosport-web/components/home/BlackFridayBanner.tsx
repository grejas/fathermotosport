"use client";

import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { ArrowRight, Zap } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { useCountdown } from "@/lib/hooks/useCountdown";
import { useMounted } from "@/lib/hooks/useMounted";
import { BLACK_FRIDAY, blackFridayPhase } from "@/lib/campaigns/blackFriday";

const bebas = Bebas_Neue({ weight: "400", subsets: ["latin"] });

/**
 * Franja de la home que lleva a /black-friday. Aparece desde el teaser (17 nov) y se
 * oculta sola al terminar la campaña. No promete porcentajes: esos los muestra la
 * página de ofertas a partir de los descuentos reales del panel.
 */
export function BlackFridayBanner() {
  const t = useTranslations("black_friday");
  const tFlash = useTranslations("flash_promo");
  const mounted = useMounted();
  const phase = blackFridayPhase();
  const live = phase === "live";
  const { segments } = useCountdown(live ? BLACK_FRIDAY.endsAt : BLACK_FRIDAY.startsAt, mounted);

  if (!mounted || (phase !== "teaser" && phase !== "live")) return null;

  return (
    <section aria-label="Black Friday" className="mx-auto max-w-7xl px-4 pt-20 sm:px-6 sm:pt-24">
      <Link
        href="/black-friday?utm_source=web&utm_medium=home_banner&utm_campaign=black_friday_2026"
        className="group relative flex flex-col gap-4 overflow-hidden rounded-2xl border border-brand-gold/30 bg-[#050505] px-5 py-5 transition hover:border-brand-gold/60 sm:flex-row sm:items-center sm:justify-between sm:px-8"
        style={{ background: "radial-gradient(circle at 90% 50%, rgba(232,0,29,0.35) 0%, transparent 55%), #050505" }}
      >
        <div className="flex items-center gap-4">
          <Zap size={28} className="shrink-0 text-brand-red" fill="#E8001D" />
          <div>
            <p className={`${bebas.className} text-3xl leading-none tracking-wide text-brand-white sm:text-4xl`}>
              BLACK <span className="text-brand-gold">FRIDAY</span>
            </p>
            <p className="mt-1 text-xs text-white/70 sm:text-sm">{live ? t("banner_live") : t("banner_teaser")}</p>
          </div>
        </div>
        <div className="flex items-center gap-4">
          <div className="flex items-end gap-1.5">
            {segments.map((seg, i) => (
              <div key={`${seg.unitKey}-${i}`} className="flex flex-col items-center">
                <span className={`${bebas.className} min-w-[2.6rem] rounded border border-brand-gold/30 bg-black/60 px-1.5 py-0.5 text-center text-2xl leading-none tabular-nums text-brand-gold`}>
                  {String(seg.value).padStart(2, "0")}
                </span>
                <span className="mt-0.5 text-[8px] font-bold uppercase text-white/60">{tFlash(seg.unitKey)}</span>
              </div>
            ))}
          </div>
          <span className="inline-flex items-center gap-1 rounded-lg bg-brand-red px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white transition-all group-hover:gap-2">
            {live ? t("banner_cta_live") : t("banner_cta_teaser")} <ArrowRight size={14} />
          </span>
        </div>
      </Link>
    </section>
  );
}

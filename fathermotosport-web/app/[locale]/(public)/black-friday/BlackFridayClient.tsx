"use client";

import { useMemo, useState } from "react";
import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { ArrowRight, MessageCircle, ShieldCheck, Truck, Zap } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { useAllProducts } from "@/lib/hooks/useAllProducts";
import { whatsappUrl } from "@/lib/api/storeSettings";
import { CONTACT_WHATSAPP } from "@/lib/data/contact";
import { trackWhatsappClick } from "@/lib/analytics/events";
import { useCountdown } from "@/lib/hooks/useCountdown";
import { useMounted } from "@/lib/hooks/useMounted";
import { BLACK_FRIDAY, blackFridayPhase } from "@/lib/campaigns/blackFriday";
import { ProductGrid } from "@/components/product/ProductGrid";
import type { Product } from "@/lib/types";

const bebas = Bebas_Neue({ weight: "400", subsets: ["latin"] });

const CLIP = "polygon(0 0, calc(100% - 28px) 0, 100% 28px, 100% 100%, 28px 100%, 0 calc(100% - 28px))";

/** Productos con descuento real: los que tienen stock primero, luego el mayor descuento. */
function discounted(all: Product[]): Product[] {
  return all
    .filter((p) => Number(p.discount_percent) > 0)
    .sort((a, b) => Number(b.in_stock) - Number(a.in_stock) || Number(b.discount_percent) - Number(a.discount_percent));
}

export function BlackFridayClient() {
  const t = useTranslations("black_friday");
  const tFlash = useTranslations("flash_promo");
  const mounted = useMounted();
  const phase = blackFridayPhase();
  const live = phase === "live";
  const target = live ? BLACK_FRIDAY.endsAt : BLACK_FRIDAY.startsAt;
  const { segments } = useCountdown(target, mounted && phase !== "ended");
  const [category, setCategory] = useState<number | null>(null);

  const { data: all, isLoading } = useAllProducts(phase !== "ended");
  const products = useMemo(() => discounted(all ?? []), [all]);

  const categories = useMemo(() => {
    const map = new Map<number, { id: number; name: string; count: number; max: number }>();
    products.forEach((p) => {
      if (!p.category) return;
      const id = Number(p.category.id);
      const cur = map.get(id) ?? { id, name: p.category.name, count: 0, max: 0 };
      cur.count += 1;
      cur.max = Math.max(cur.max, Number(p.discount_percent));
      map.set(id, cur);
    });
    return [...map.values()].sort((a, b) => b.count - a.count);
  }, [products]);

  const visible = category ? products.filter((p) => Number(p.category?.id) === category) : products;
  const maxDiscount = products.reduce((m, p) => Math.max(m, Number(p.discount_percent)), 0);

  const waHref = whatsappUrl(CONTACT_WHATSAPP, live ? t("wa_live") : t("wa_teaser")) ?? `https://wa.me/${CONTACT_WHATSAPP}`;

  if (!mounted) return <div className="min-h-screen" />;

  if (phase === "ended") {
    return (
      <div className="mx-auto flex min-h-[70vh] max-w-xl flex-col items-center justify-center px-4 text-center">
        <h1 className={`${bebas.className} text-5xl text-brand-white`}>{t("ended_title")}</h1>
        <p className="mt-3 text-brand-muted">{t("ended_text")}</p>
        <Link href="/catalog" className="btn-primary mt-6 inline-flex items-center gap-2">
          {t("see_catalog")} <ArrowRight size={16} />
        </Link>
      </div>
    );
  }

  return (
    <div className="pb-16 pt-20 sm:pt-24">
      {/* HERO */}
      <section className="mx-auto max-w-7xl px-4 sm:px-6">
        <div style={{ filter: "drop-shadow(0 0 32px rgba(232,0,29,0.35))" }}>
          <div className="p-px" style={{ clipPath: CLIP, background: "linear-gradient(135deg, #E8C766, rgba(201,168,76,0.2) 45%, #E8001D)" }}>
            <div
              className="relative overflow-hidden px-5 py-10 sm:px-12 sm:py-14"
              style={{
                clipPath: CLIP,
                background:
                  "radial-gradient(circle at 85% 15%, rgba(232,0,29,0.40) 0%, transparent 50%), " +
                  "radial-gradient(circle at 10% 95%, rgba(201,168,76,0.16) 0%, transparent 50%), " +
                  "repeating-linear-gradient(115deg, rgba(255,255,255,0.025) 0 2px, transparent 2px 14px), #050505",
              }}
            >
              <span
                aria-hidden
                className="pointer-events-none absolute inset-0 animate-flash-shine"
                style={{ background: "linear-gradient(105deg, transparent 42%, rgba(255,255,255,0.09) 50%, transparent 58%)" }}
              />
              <div className="relative grid items-center gap-10 lg:grid-cols-[1.25fr_1fr]">
                <div>
                  <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-gold/40 bg-black/50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-brand-gold">
                    <Zap size={12} className="text-brand-red" fill="#E8001D" />
                    {live ? t("eyebrow_live") : t("eyebrow_teaser")}
                  </span>
                  <h1 className="mt-4 leading-[0.85]">
                    <span className={`${bebas.className} block text-[4.5rem] tracking-wide text-brand-white sm:text-[7.5rem]`}>BLACK</span>
                    <span
                      className={`${bebas.className} block text-[4.5rem] tracking-wide sm:text-[7.5rem]`}
                      style={{
                        backgroundImage: "linear-gradient(180deg, #FFF3C4 0%, #E8C766 45%, #A8832B 100%)",
                        WebkitBackgroundClip: "text",
                        backgroundClip: "text",
                        color: "transparent",
                      }}
                    >
                      FRIDAY
                    </span>
                  </h1>
                  <p className="mt-5 max-w-lg text-sm text-white/80 sm:text-base">
                    {live && maxDiscount > 0 ? (
                      <>
                        <span className="font-extrabold text-brand-gold">{t("up_to", { pct: maxDiscount })}</span> {t("subtitle_live")}
                      </>
                    ) : (
                      t("subtitle_teaser")
                    )}
                  </p>
                  <p className="mt-2 text-xs text-white/50">{t("dates")}</p>
                </div>

                <div className="rounded-2xl border border-white/10 bg-black/50 p-5 backdrop-blur sm:p-6">
                  <p className="mb-3 text-[10px] font-bold uppercase tracking-[0.22em] text-white/70">
                    {live ? t("ends_in") : t("starts_in")}
                  </p>
                  <div className="flex items-end gap-2">
                    {segments.map((seg, i) => (
                      <div key={`${seg.unitKey}-${i}`} className="flex flex-1 flex-col items-center">
                        <span
                          className={`${bebas.className} w-full rounded-md border border-brand-gold/30 bg-black/60 px-2 py-2 text-center text-4xl leading-none tabular-nums text-brand-gold sm:text-5xl`}
                          style={{ textShadow: "0 0 14px rgba(201,168,76,0.6)" }}
                        >
                          {String(seg.value).padStart(2, "0")}
                        </span>
                        <span className="mt-1 text-[9px] font-bold uppercase tracking-wide text-white/70">{tFlash(seg.unitKey)}</span>
                      </div>
                    ))}
                  </div>
                  <a
                    href={waHref}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={() => trackWhatsappClick(live ? "black_friday_live" : "black_friday_teaser")}
                    className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 text-sm font-bold text-black transition hover:brightness-110"
                  >
                    <MessageCircle size={18} />
                    {live ? t("cta_wa_live") : t("cta_wa_teaser")}
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Confianza: solo lo que la tienda realmente ofrece */}
        <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
          {[
            { Icon: Truck, text: t("trust_shipping") },
            { Icon: ShieldCheck, text: t("trust_payment") },
            { Icon: MessageCircle, text: t("trust_support") },
          ].map(({ Icon, text }) => (
            <div key={text} className="flex items-center gap-3 rounded-xl border border-white/10 bg-brand-card px-4 py-3">
              <Icon size={18} className="shrink-0 text-brand-gold" />
              <span className="text-xs font-semibold text-brand-white sm:text-sm">{text}</span>
            </div>
          ))}
        </div>
      </section>

      {/* OFERTAS */}
      <section className="mx-auto mt-12 max-w-7xl px-4 sm:px-6">
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
          <h2 className={`${bebas.className} text-4xl tracking-wide text-brand-white`}>
            {live ? t("deals_title") : t("already_title")}
          </h2>
          {products.length > 0 && <span className="text-xs text-brand-muted">{t("count", { n: visible.length })}</span>}
        </div>

        {categories.length > 1 && (
          <div className="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            <button
              type="button"
              onClick={() => setCategory(null)}
              className={`shrink-0 rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-wide transition ${
                category === null ? "border-brand-red bg-brand-red text-white" : "border-white/15 text-white/80 hover:border-brand-gold/60"
              }`}
            >
              {t("all")}
            </button>
            {categories.map((c) => (
              <button
                key={c.id}
                type="button"
                onClick={() => setCategory(c.id)}
                className={`shrink-0 rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-wide transition ${
                  category === c.id ? "border-brand-red bg-brand-red text-white" : "border-white/15 text-white/80 hover:border-brand-gold/60"
                }`}
              >
                {c.name} <span className="ml-1 text-brand-gold">-{c.max}%</span>
              </button>
            ))}
          </div>
        )}

        {isLoading || products.length > 0 ? (
          <ProductGrid products={visible} loading={isLoading} />
        ) : (
          <div className="rounded-2xl border border-dashed border-white/15 bg-brand-card px-6 py-12 text-center">
            <p className="text-brand-white">{live ? t("empty_live") : t("empty_teaser")}</p>
            <Link href="/catalog" className="mt-4 inline-flex items-center gap-2 text-sm font-bold text-brand-gold hover:underline">
              {t("see_catalog")} <ArrowRight size={14} />
            </Link>
          </div>
        )}

        <p className="mt-8 text-center text-[11px] text-white/45">{t("fine_print")}</p>
      </section>
    </div>
  );
}

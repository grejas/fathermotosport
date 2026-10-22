"use client";

import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from "react";
import { useTranslations } from "next-intl";
import type { Product, ReviewsSummary } from "@/lib/types";
import { cn } from "@/lib/utils";
import { ProductDescription } from "./ProductDescription";
import { RatingSummary } from "./RatingSummary";
import { ReviewCard } from "./ReviewCard";
import { ReviewForm } from "./ReviewForm";

type Tab = "reviews" | "description" | "specs";

/** Ancla de la pestaña de reseñas (/product/slug#reviews). */
export const REVIEWS_ANCHOR = "reviews";
/** Evento que pide abrir la pestaña de reseñas (lo lanza "N reseñas" de ProductInfo). */
export const SHOW_REVIEWS_EVENT = "product:show-reviews";

export function ProductTabs({ product }: { product: Product }) {
  const t = useTranslations("reviews");
  // Reseñas primero y abierta por defecto, aunque no haya ninguna (se ve el formulario).
  const [tab, setTab] = useState<Tab>("reviews");
  const idBase = useId();
  const contenedorRef = useRef<HTMLDivElement>(null);
  const botonesRef = useRef<Partial<Record<Tab, HTMLButtonElement | null>>>({});
  const specs = product.specs ?? {};
  const reviews = useMemo(() => product.reviews ?? [], [product.reviews]);

  // El backend no expone distribución por estrella a nivel de producto (solo
  // rating_avg y reviews_count), así que se calcula acá a partir de las
  // reseñas aprobadas ya incluidas en el detalle del producto.
  const summary: ReviewsSummary = useMemo(() => {
    const total = product.reviews_count ?? reviews.length;
    const distribution = [5, 4, 3, 2, 1].map((rating) => {
      const count = reviews.filter((r) => r.rating === rating).length;
      return { rating, count, percent: total > 0 ? Math.round((count / total) * 100) : 0 };
    });
    return { average: product.rating_avg ?? 0, total, distribution };
  }, [product.reviews_count, product.rating_avg, reviews]);

  const tabs: { id: Tab; label: string }[] = [
    { id: "reviews", label: t("tab_label") },
    { id: "description", label: "Descripción" },
    { id: "specs", label: "Especificaciones" },
  ];

  // "N reseñas" de la ficha (o un enlace a #reviews): abre la pestaña y baja hasta ella.
  useEffect(() => {
    const mostrarResenas = () => {
      setTab("reviews");
      const quieto = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      contenedorRef.current?.scrollIntoView({ behavior: quieto ? "auto" : "smooth", block: "start" });
      botonesRef.current.reviews?.focus({ preventScroll: true });
    };
    const alCambiarHash = () => {
      if (window.location.hash === `#${REVIEWS_ANCHOR}`) mostrarResenas();
    };
    alCambiarHash();
    window.addEventListener(SHOW_REVIEWS_EVENT, mostrarResenas);
    window.addEventListener("hashchange", alCambiarHash);
    return () => {
      window.removeEventListener(SHOW_REVIEWS_EVENT, mostrarResenas);
      window.removeEventListener("hashchange", alCambiarHash);
    };
  }, []);

  // Patrón de pestañas WAI-ARIA: flechas, Inicio y Fin mueven el foco y activan la pestaña.
  const alPulsarTecla = (e: KeyboardEvent<HTMLButtonElement>) => {
    const actual = tabs.findIndex((tb) => tb.id === tab);
    const destino =
      e.key === "ArrowRight" ? (actual + 1) % tabs.length
      : e.key === "ArrowLeft" ? (actual - 1 + tabs.length) % tabs.length
      : e.key === "Home" ? 0
      : e.key === "End" ? tabs.length - 1
      : -1;
    if (destino < 0) return;
    e.preventDefault();
    setTab(tabs[destino].id);
    botonesRef.current[tabs[destino].id]?.focus();
  };

  return (
    <div id={REVIEWS_ANCHOR} ref={contenedorRef} className="mt-12 scroll-mt-24">
      <div role="tablist" aria-label={product.name} className="flex gap-1 border-b border-white/10">
        {tabs.map((tb) => (
          <button
            key={tb.id}
            ref={(el) => {
              botonesRef.current[tb.id] = el;
            }}
            type="button"
            role="tab"
            id={`${idBase}-tab-${tb.id}`}
            aria-selected={tab === tb.id}
            aria-controls={tab === tb.id ? `${idBase}-panel` : undefined}
            tabIndex={tab === tb.id ? 0 : -1}
            onClick={() => setTab(tb.id)}
            onKeyDown={alPulsarTecla}
            className={cn(
              "px-4 py-3 text-sm font-semibold transition",
              tab === tb.id
                ? "border-b-2 border-brand-red text-brand-white"
                : "text-brand-muted hover:text-brand-white"
            )}
          >
            {tb.label}
          </button>
        ))}
      </div>

      {/* Un solo panel con el contenido de la pestaña activa (mismo orden que las pestañas). */}
      <div
        role="tabpanel"
        id={`${idBase}-panel`}
        aria-labelledby={`${idBase}-tab-${tab}`}
        tabIndex={0}
        className="py-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red/60"
      >
        {tab === "reviews" && (
          <div className="space-y-8">
            {reviews.length > 0 ? (
              <>
                <RatingSummary summary={summary} />
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                  {reviews.map((r) => (
                    <ReviewCard key={r.id} review={r} />
                  ))}
                </div>
              </>
            ) : (
              <div className="rounded-2xl border border-white/[0.06] bg-brand-card p-8 text-center">
                <p className="text-brand-muted">{t("empty_state")}</p>
              </div>
            )}

            <div className="mx-auto max-w-xl border-t border-white/10 pt-8">
              <h3 className="mb-4 text-center text-lg font-bold uppercase tracking-wide text-brand-red">
                {t("leave_review")}
              </h3>
              <ReviewForm product={product} />
            </div>
          </div>
        )}

        {tab === "description" && <ProductDescription html={product.description} />}

        {tab === "specs" && (
          <div className="overflow-hidden rounded-xl border border-white/10">
            {Object.keys(specs).length === 0 ? (
              <p className="p-4 text-brand-muted">Sin especificaciones registradas.</p>
            ) : (
              <table className="w-full text-sm">
                <tbody>
                  {Object.entries(specs).map(([key, value], i) => (
                    <tr key={key} className={i % 2 ? "bg-white/[0.02]" : ""}>
                      <td className="w-1/3 px-4 py-3 font-medium capitalize text-brand-white">{key}</td>
                      <td className="px-4 py-3 text-brand-muted">{String(value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

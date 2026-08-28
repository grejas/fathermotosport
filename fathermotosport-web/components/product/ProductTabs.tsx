"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import type { Product, ReviewsSummary } from "@/lib/types";
import { cn } from "@/lib/utils";
import { ProductDescription } from "./ProductDescription";
import { RatingSummary } from "./RatingSummary";
import { ReviewCard } from "./ReviewCard";
import { ReviewForm } from "./ReviewForm";

type Tab = "description" | "specs" | "reviews";

export function ProductTabs({ product }: { product: Product }) {
  const t = useTranslations("reviews");
  const [tab, setTab] = useState<Tab>("description");
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
    { id: "description", label: "Descripción" },
    { id: "specs", label: "Especificaciones" },
    { id: "reviews", label: t("tab_label") },
  ];

  return (
    <div className="mt-12">
      <div className="flex gap-1 border-b border-white/10">
        {tabs.map((tb) => (
          <button
            key={tb.id}
            onClick={() => setTab(tb.id)}
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

      <div className="py-6">
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
      </div>
    </div>
  );
}

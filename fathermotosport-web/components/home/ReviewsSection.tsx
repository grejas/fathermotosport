"use client";

import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { useReviews, useReviewsSummary } from "@/lib/hooks/useReviews";
import { RatingSummary } from "@/components/product/RatingSummary";
import { ReviewCard } from "@/components/product/ReviewCard";
import { ReviewForm } from "@/components/product/ReviewForm";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

/**
 * Sección global de reseñas ("Lo que dicen nuestros clientes"): combina
 * reseñas aprobadas de TODOS los productos, a diferencia de la antigua
 * pestaña de reseñas por producto (eliminada de ProductTabs).
 */
export function ReviewsSection() {
  const t = useTranslations("reviews");
  const { data: summary } = useReviewsSummary();
  const { data: reviewsData } = useReviews(1);
  const reviews = reviewsData?.data ?? [];
  const hasReviews = (summary?.total ?? 0) > 0;

  return (
    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <div className="mb-8 text-center">
        <h2 className={`${bebasNeue.className} text-4xl uppercase tracking-wide text-brand-white sm:text-5xl`}>
          {t("section_title")}
        </h2>
      </div>

      {hasReviews ? (
        <>
          {summary && <RatingSummary summary={summary} />}
          <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {reviews.slice(0, 6).map((r) => (
              <ReviewCard key={r.id} review={r} />
            ))}
          </div>
        </>
      ) : (
        <div className="rounded-2xl border border-white/[0.06] bg-brand-card p-8 text-center">
          <p className="text-brand-muted">{t("empty_state")}</p>
        </div>
      )}

      <div className="mx-auto mt-10 max-w-xl border-t border-white/10 pt-8">
        <h3 className={`${bebasNeue.className} mb-4 text-center text-2xl uppercase tracking-wide text-brand-red`}>
          {t("leave_review")}
        </h3>
        <ReviewForm />
      </div>
    </section>
  );
}

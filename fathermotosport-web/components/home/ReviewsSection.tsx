"use client";

import { useRef, useState } from "react";
import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { useReviews, useReviewsSummary } from "@/lib/hooks/useReviews";
import { RatingSummary } from "@/components/product/RatingSummary";
import { ReviewCard } from "@/components/product/ReviewCard";
import { ReviewForm } from "@/components/product/ReviewForm";
import { Button } from "@/components/ui/Button";
// TEMPORAL: 7 reseñas de prueba para verificar visualmente el diseño mientras
// el sistema real está en fase de pruebas. Ver lib/data/demoReviews.ts para
// instrucciones de cómo quitarlas.
import { demoReviews, demoReviewsSummary } from "@/lib/data/demoReviews";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });
const INITIAL_VISIBLE_REVIEWS = 9;

/**
 * Sección global de reseñas ("Lo que dicen nuestros clientes"): combina
 * reseñas aprobadas de TODOS los productos, a diferencia de la antigua
 * pestaña de reseñas por producto (eliminada de ProductTabs).
 */
export function ReviewsSection() {
  const t = useTranslations("reviews");
  const sectionRef = useRef<HTMLElement>(null);
  const { data: summary } = useReviewsSummary();
  // per_page en 60 (el máximo que acepta el backend) para que "Ver más
  // reseñas" solo revele reseñas ya cargadas en memoria, sin pedir otra
  // página a la API.
  const { data: reviewsData } = useReviews(1);
  const [isExpanded, setIsExpanded] = useState(false);

  // TEMPORAL: se anteponen las demoReviews a las reseñas reales para QA
  // visual. Al quitarlas, dejar solo: const reviews = reviewsData?.data ?? [];
  // y `const displaySummary = summary;` (usar `summary` directo más abajo).
  const reviews = [...demoReviews, ...(reviewsData?.data ?? [])];
  const displaySummary = summary && summary.total > 0 ? summary : demoReviewsSummary;
  const hasReviews = reviews.length > 0;
  const canToggle = reviews.length > INITIAL_VISIBLE_REVIEWS;
  const visibleReviews = isExpanded ? reviews : reviews.slice(0, INITIAL_VISIBLE_REVIEWS);

  const handleToggle = () => {
    if (isExpanded) {
      setIsExpanded(false);
      sectionRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      setIsExpanded(true);
    }
  };

  return (
    <section ref={sectionRef} className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <div className="mb-8 text-center">
        <h2 className={`${bebasNeue.className} text-4xl uppercase tracking-wide text-brand-white sm:text-5xl`}>
          {t("section_title")}
        </h2>
      </div>

      {hasReviews ? (
        <>
          <RatingSummary summary={displaySummary} />
          <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {visibleReviews.map((r) => (
              <ReviewCard key={r.id} review={r} />
            ))}
          </div>
          {canToggle && (
            <div className="mt-8 text-center">
              <Button variant="glass" size="sm" onClick={handleToggle}>
                {isExpanded ? t("show_less") : t("show_more")}
              </Button>
            </div>
          )}
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

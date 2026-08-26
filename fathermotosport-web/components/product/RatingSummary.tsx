"use client";

import { useTranslations } from "next-intl";
import { Star } from "lucide-react";
import type { ReviewsSummary } from "@/lib/types";

export function RatingSummary({ summary }: { summary: ReviewsSummary }) {
  const t = useTranslations("reviews");
  const { average, total, distribution } = summary;

  return (
    <div className="rounded-2xl border border-white/[0.06] bg-brand-card p-6">
      <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
        <div className="flex shrink-0 flex-col items-center gap-1 sm:items-start">
          <span className="text-5xl font-extrabold text-brand-white">{average.toFixed(1)}</span>
          <div className="flex">
            {Array.from({ length: 5 }).map((_, i) => (
              <Star
                key={i}
                size={18}
                className={i < Math.round(average) ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
              />
            ))}
          </div>
          <span className="text-xs text-brand-muted">{t("count", { count: total })}</span>
        </div>

        <div className="flex-1 space-y-2">
          {distribution.map(({ rating, percent }) => (
            <div key={rating} className="flex items-center gap-3">
              <span className="w-8 shrink-0 text-xs font-semibold text-brand-muted">{rating}★</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-white/[0.06]">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-brand-red to-brand-gold transition-all duration-500"
                  style={{ width: `${percent}%` }}
                />
              </div>
              <span className="w-9 shrink-0 text-right text-xs text-brand-muted">{percent}%</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

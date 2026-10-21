"use client";

import { useLocale, useTranslations } from "next-intl";
import { Globe, Star } from "lucide-react";
import type { Review } from "@/lib/types";
import { formatDate } from "@/lib/utils";

// Paleta tomada de las categorías del sitio (tailwind.config.ts → cat.*) más
// brand-red/gold, para que los avatares combinen con el resto del sistema.
const AVATAR_COLORS = ["#E8001D", "#C9A84C", "#6C63FF", "#00C897", "#FF7A00", "#00B4D8"];

/** Hash simple del nombre → color consistente por usuario. */
function avatarColor(name: string): string {
  const hash = Array.from(name).reduce((acc, char) => acc + char.charCodeAt(0), 0);
  return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}

export function reviewerName(review: Review, fallback: string): string {
  return review.user?.name ?? fallback;
}

/** Estrellas de la calificación, con texto para lectores de pantalla. */
export function ReviewStars({ rating, size = 14 }: { rating: number; size?: number }) {
  const t = useTranslations("reviews");

  return (
    <div className="flex" role="img" aria-label={t("rating_label", { rating })}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          size={size}
          aria-hidden
          className={i < rating ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
        />
      ))}
    </div>
  );
}

/** Avatar de iniciales, nombre con bandera del país y fecha. */
export function ReviewAuthor({ review, nameId }: { review: Review; nameId?: string }) {
  const locale = useLocale();
  const t = useTranslations("reviews");
  const name = reviewerName(review, t("anonymous_customer"));
  const countryCode = review.user?.country?.toLowerCase() || null;

  return (
    <div className="flex min-w-0 items-center gap-3">
      <span
        aria-hidden
        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
        style={{ backgroundColor: avatarColor(name) }}
      >
        {initials(name)}
      </span>
      <div className="min-w-0">
        <p id={nameId} className="flex items-center gap-1.5 text-sm font-semibold text-brand-white">
          <span className="truncate">{name}</span>
          {countryCode ? (
            <span aria-hidden className={`fi fi-${countryCode} h-3.5 w-5 shrink-0 rounded-sm bg-cover bg-center`} />
          ) : (
            <Globe size={13} className="shrink-0 text-brand-muted" aria-label={t("unknown_country")}>
              <title>{t("unknown_country")}</title>
            </Globe>
          )}
        </p>
        <p className="text-xs text-brand-muted">
          <time dateTime={review.created_at}>{formatDate(review.created_at, locale)}</time>
        </p>
      </div>
    </div>
  );
}

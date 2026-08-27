"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Globe, Star } from "lucide-react";
import type { Review } from "@/lib/types";
import { formatDate } from "@/lib/utils";
import { translateText } from "@/lib/utils/translate";

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

export function ReviewCard({ review }: { review: Review }) {
  const locale = useLocale();
  const t = useTranslations("reviews");
  const name = review.user?.name ?? t("anonymous_customer");
  const countryCode = review.user?.country?.toLowerCase() || null;
  const [translated, setTranslated] = useState<{ title: string | null; comment: string | null } | null>(null);

  // Mismo patrón que ProductClient.tsx: traduce silenciosamente title/comment
  // al idioma activo (pt/en); en 'es' no se traduce nada. Si skip_translation
  // es true (reseña escrita originalmente en otro idioma), nunca se traduce,
  // bajo ningún locale.
  useEffect(() => {
    if (review.skip_translation || locale === "es") {
      setTranslated(null);
      return;
    }
    let cancelled = false;
    (async () => {
      const [title, comment] = await Promise.all([
        review.title ? translateText(review.title, locale) : Promise.resolve(review.title),
        review.comment ? translateText(review.comment, locale) : Promise.resolve(review.comment),
      ]);
      if (!cancelled) setTranslated({ title, comment });
    })();
    return () => {
      cancelled = true;
    };
  }, [review, locale]);

  const displayTitle = translated ? translated.title : review.title;
  const displayComment = translated ? translated.comment : review.comment;

  return (
    <div className="card-product h-full p-5">
      <div className="flex items-center gap-3">
        <span
          className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
          style={{ backgroundColor: avatarColor(name) }}
        >
          {initials(name)}
        </span>
        <div className="min-w-0">
          <p className="flex items-center gap-1.5 text-sm font-semibold text-brand-white">
            <span className="truncate">{name}</span>
            {countryCode ? (
              <span
                aria-hidden
                className={`fi fi-${countryCode} h-3.5 w-5 shrink-0 rounded-sm bg-cover bg-center`}
              />
            ) : (
              <Globe
                size={13}
                className="shrink-0 text-brand-muted"
                aria-label={t("unknown_country")}
              >
                <title>{t("unknown_country")}</title>
              </Globe>
            )}
          </p>
          <p className="text-xs text-brand-muted">{formatDate(review.created_at, locale)}</p>
        </div>
        <div className="ml-auto flex shrink-0">
          {Array.from({ length: 5 }).map((_, i) => (
            <Star
              key={i}
              size={13}
              className={i < review.rating ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
            />
          ))}
        </div>
      </div>

      {displayTitle && <p className="mt-3 text-sm font-semibold text-brand-white">{displayTitle}</p>}
      {displayComment && <p className="mt-1 text-sm text-brand-muted">{displayComment}</p>}

      {review.product && (
        <p className="mt-3 border-t border-white/10 pt-3 text-xs text-brand-muted">
          {t("about_product")}: <span className="font-medium text-brand-white">{review.product.name}</span>
        </p>
      )}
    </div>
  );
}

"use client";

import { useEffect, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import type { Review } from "@/lib/types";
import { translateText } from "@/lib/utils/translate";
import { ReviewAuthor, ReviewStars, reviewerName } from "./ReviewAuthor";
import { ReviewDialog } from "./ReviewDialog";

export function ReviewCard({ review }: { review: Review }) {
  const locale = useLocale();
  const t = useTranslations("reviews");
  const [translated, setTranslated] = useState<{ title: string | null; comment: string | null } | null>(null);
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const images = review.images ?? [];
  const cover = images[0];

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
    <article className="card-product relative flex h-full flex-col p-5 transition hover:border-white/20">
      <ReviewStars rating={review.rating} />

      <div className="mt-3">
        <ReviewAuthor review={review} />
      </div>

      {displayTitle && <p className="mt-3 text-sm font-semibold text-brand-white">{displayTitle}</p>}
      {displayComment && <p className="mt-1 line-clamp-4 text-sm text-brand-muted">{displayComment}</p>}

      {cover && (
        <div className="relative mt-4 h-20 w-20 overflow-hidden rounded-lg border border-white/10 bg-black">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={cover.thumb_url} alt="" loading="lazy" className="h-full w-full object-cover" />
          {images.length > 1 && (
            <span className="absolute bottom-1 right-1 rounded bg-black/75 px-1.5 text-[11px] font-semibold text-white">
              +{images.length - 1}
            </span>
          )}
        </div>
      )}

      {review.product && (
        <div className="mt-auto pt-4">
          <p className="border-t border-white/10 pt-3 text-xs text-brand-muted">
            {t("about_product")}: <span className="font-medium text-brand-white">{review.product.name}</span>
          </p>
        </div>
      )}

      {/* Toda la tarjeta abre el detalle: un botón estirado sobre ella (accesible con teclado). */}
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setOpen(true)}
        aria-haspopup="dialog"
        aria-label={t("view_review", { name: reviewerName(review, t("anonymous_customer")) })}
        className="absolute inset-0 rounded-[inherit] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold"
      />

      {open && (
        <ReviewDialog
          review={review}
          title={displayTitle}
          comment={displayComment}
          onClose={() => {
            setOpen(false);
            triggerRef.current?.focus();
          }}
        />
      )}
    </article>
  );
}

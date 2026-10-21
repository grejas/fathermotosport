"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useTranslations } from "next-intl";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import type { Review } from "@/lib/types";
import { cn } from "@/lib/utils";
import { ReviewAuthor, ReviewStars, reviewerName } from "./ReviewAuthor";

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Detalle de una reseña: foto grande a la izquierda con miniaturas debajo, y a la
 * derecha estrellas, autor, fecha y texto. En pantallas chicas va todo en una
 * columna. Diálogo modal accesible: foco atrapado, Escape cierra, ← → cambian de
 * foto. Se monta solo mientras está abierto (lo controla ReviewCard, que devuelve
 * el foco a la tarjeta al cerrar).
 */
export function ReviewDialog({
  review,
  title,
  comment,
  onClose,
}: {
  review: Review;
  title: string | null;
  comment: string | null;
  onClose: () => void;
}) {
  const t = useTranslations("reviews");
  const images = review.images ?? [];
  const hasImages = images.length > 0;
  const [index, setIndex] = useState(0);
  const panelRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const nameId = useId();
  const textId = useId();
  const name = reviewerName(review, t("anonymous_customer"));

  const go = useCallback(
    (delta: number) => setIndex((i) => (i + delta + images.length) % images.length),
    [images.length]
  );

  // Bloquea el scroll de la página y pone el foco en el botón de cerrar.
  useEffect(() => {
    const previo = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeRef.current?.focus();
    return () => {
      document.body.style.overflow = previo;
    };
  }, []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        onClose();
        return;
      }
      if (images.length > 1 && (e.key === "ArrowLeft" || e.key === "ArrowRight")) {
        e.preventDefault();
        go(e.key === "ArrowLeft" ? -1 : 1);
        return;
      }
      if (e.key !== "Tab" || !panelRef.current) return;

      // Foco atrapado: de la última parada vuelve a la primera y al revés.
      const paradas = Array.from(panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE));
      if (paradas.length === 0) return;
      const primera = paradas[0];
      const ultima = paradas[paradas.length - 1];
      const activo = document.activeElement;
      if (!panelRef.current.contains(activo)) {
        e.preventDefault();
        primera.focus();
      } else if (e.shiftKey && activo === primera) {
        e.preventDefault();
        ultima.focus();
      } else if (!e.shiftKey && activo === ultima) {
        e.preventDefault();
        primera.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [go, images.length, onClose]);

  const current = images[index];

  return createPortal(
    <div className="fixed inset-0 z-[100] flex items-center justify-center sm:p-4">
      <div className="absolute inset-0 bg-black/85 backdrop-blur-sm" onClick={onClose} aria-hidden />

      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={nameId}
        aria-describedby={comment ? textId : undefined}
        className={cn(
          "relative z-10 flex h-full w-full flex-col overflow-y-auto bg-brand-dark shadow-card-hover sm:h-auto sm:max-h-[90vh] sm:rounded-2xl sm:border sm:border-white/10",
          hasImages ? "sm:max-w-3xl lg:grid lg:h-[min(90vh,760px)] lg:max-w-5xl lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:grid-rows-[minmax(0,1fr)] lg:overflow-hidden" : "sm:max-w-lg"
        )}
      >
        <button
          ref={closeRef}
          type="button"
          onClick={onClose}
          aria-label={t("close")}
          className="absolute right-3 top-3 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold"
        >
          <X size={20} aria-hidden />
        </button>

        {hasImages && current && (
          <div className="flex shrink-0 flex-col bg-black lg:min-h-0">
            <div className="relative flex h-[55vh] items-center justify-center sm:h-[60vh] lg:h-auto lg:min-h-0 lg:flex-1">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                key={current.id}
                src={current.url}
                width={current.width}
                height={current.height}
                alt={t("photo_alt", { n: index + 1, total: images.length, name })}
                className="max-h-full max-w-full object-contain"
              />

              {images.length > 1 && (
                <>
                  <button
                    type="button"
                    onClick={() => go(-1)}
                    aria-label={t("previous_photo")}
                    className="absolute left-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold"
                  >
                    <ChevronLeft size={22} aria-hidden />
                  </button>
                  <button
                    type="button"
                    onClick={() => go(1)}
                    aria-label={t("next_photo")}
                    className="absolute right-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold"
                  >
                    <ChevronRight size={22} aria-hidden />
                  </button>
                  <p
                    aria-live="polite"
                    className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-2.5 py-0.5 text-xs text-white"
                  >
                    {t("photo_counter", { n: index + 1, total: images.length })}
                  </p>
                </>
              )}
            </div>

            {images.length > 1 && (
              <div className="flex gap-2 overflow-x-auto border-t border-white/10 p-3">
                {images.map((img, i) => (
                  <button
                    key={img.id}
                    type="button"
                    onClick={() => setIndex(i)}
                    aria-label={t("show_photo", { n: i + 1 })}
                    aria-current={i === index ? "true" : undefined}
                    className={cn(
                      "h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold",
                      i === index ? "border-brand-gold" : "border-transparent opacity-60 hover:opacity-100"
                    )}
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={img.thumb_url} alt="" className="h-full w-full object-cover" />
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        <div className="flex flex-col gap-4 p-6 pr-14 lg:overflow-y-auto">
          <ReviewStars rating={review.rating} size={18} />
          <ReviewAuthor review={review} nameId={nameId} />
          {title && <h3 className="text-base font-semibold text-brand-white">{title}</h3>}
          {comment && (
            <p id={textId} className="whitespace-pre-line text-sm leading-relaxed text-brand-muted">
              {comment}
            </p>
          )}
          {review.product && (
            <p className="border-t border-white/10 pt-3 text-xs text-brand-muted">
              {t("about_product")}: <span className="font-medium text-brand-white">{review.product.name}</span>
            </p>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

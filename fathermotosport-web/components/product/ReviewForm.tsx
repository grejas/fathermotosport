"use client";

import { useEffect, useMemo, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { Star } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { useAuthStore } from "@/store/authStore";
import { useCreateReview } from "@/lib/hooks/useCreateReview";
import { useProducts } from "@/lib/hooks/useProducts";
import type { Product } from "@/lib/types";

function ProductSelect({
  value,
  onChange,
}: {
  value: Product | null;
  onChange: (product: Product | null) => void;
}) {
  const t = useTranslations("reviews");
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const { data, isLoading } = useProducts({ per_page: 60, sort: "name_asc" });

  const filtered = useMemo(() => {
    const products = data?.data ?? [];
    const term = query.trim().toLowerCase();
    if (!term) return products;
    return products.filter((p) => p.name.toLowerCase().includes(term));
  }, [data, query]);

  return (
    <div className="relative mb-3">
      <input
        type="text"
        value={value ? value.name : query}
        onChange={(e) => {
          onChange(null);
          setQuery(e.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        placeholder={t("search_product_placeholder")}
        className="input-brand"
      />
      {open && (
        <div className="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-white/10 bg-brand-dark shadow-card">
          {isLoading && <p className="p-3 text-xs text-brand-muted">{t("loading_products")}</p>}
          {!isLoading && filtered.length === 0 && (
            <p className="p-3 text-xs text-brand-muted">{t("no_results")}</p>
          )}
          {filtered.map((p) => (
            <button
              key={p.id}
              type="button"
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => {
                onChange(p);
                setQuery("");
                setOpen(false);
              }}
              className="block w-full px-3 py-2 text-left text-sm text-brand-white transition hover:bg-white/5"
            >
              {p.name}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export function ReviewForm({ product: fixedProduct }: { product?: Product } = {}) {
  const t = useTranslations("reviews");
  const isAuth = useAuthStore((s) => s.isAuth);
  const { mutate, isPending } = useCreateReview();

  const [product, setProduct] = useState<Product | null>(fixedProduct ?? null);
  const [rating, setRating] = useState(0);
  const [hoverRating, setHoverRating] = useState(0);
  const [title, setTitle] = useState("");
  const [comment, setComment] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Mantiene el producto fijo sincronizado si el usuario navega a otro
  // producto sin remount (ej. desde RelatedProducts), ya que ProductClient
  // reutiliza esta misma instancia de ReviewForm. Se usa fixedProduct?.slug
  // (no el objeto) porque ProductClient recrea el objeto `product` en cada
  // render una vez que llega la traducción, y eso dispararía este efecto
  // en cada render perdiendo lo que el usuario esté escribiendo.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { if (fixedProduct) setProduct(fixedProduct); }, [fixedProduct?.slug]);

  if (!isAuth) {
    return (
      <div className="rounded-xl border border-white/10 bg-brand-card p-5 text-center">
        <Link href="/login">
          <Button variant="glass" size="sm">
            {t("login_to_review")}
          </Button>
        </Link>
      </div>
    );
  }

  if (submitted) {
    return (
      <div className="rounded-xl border border-brand-gold/30 bg-brand-gold/10 p-5 text-center text-sm font-medium text-brand-gold">
        {t("success_message")}
      </div>
    );
  }

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!product) {
      setErrorMessage(t("select_product_error"));
      return;
    }
    if (rating < 1) {
      setErrorMessage(t("select_rating_error"));
      return;
    }

    mutate(
      {
        slug: product.slug,
        rating,
        title: title.trim() || undefined,
        comment: comment.trim() || undefined,
      },
      {
        onSuccess: () => setSubmitted(true),
        onError: (err) => {
          // 'code' es machine-readable (backend); si viene, mostramos nuestra
          // propia traducción en vez del 'message' fijo en español.
          const response = (err as { response?: { data?: { message?: string; code?: string } } }).response;
          const msg =
            response?.data?.code === "already_reviewed"
              ? t("already_reviewed")
              : response?.data?.message ?? t("submit_error");
          setErrorMessage(msg);
        },
      }
    );
  };

  return (
    <form onSubmit={handleSubmit} className="rounded-xl border border-white/10 bg-brand-card p-5">
      {!fixedProduct && <ProductSelect value={product} onChange={setProduct} />}

      <div className="mb-4 flex items-center gap-1">
        {Array.from({ length: 5 }).map((_, i) => {
          const value = i + 1;
          const active = value <= (hoverRating || rating);
          return (
            <button
              key={value}
              type="button"
              onClick={() => setRating(value)}
              onMouseEnter={() => setHoverRating(value)}
              onMouseLeave={() => setHoverRating(0)}
              className="p-0.5"
              aria-label={`${value} ${t("stars_label")}`}
            >
              <Star size={26} className={active ? "fill-brand-gold text-brand-gold" : "text-brand-muted"} />
            </button>
          );
        })}
      </div>

      <input
        type="text"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder={t("title_placeholder")}
        maxLength={255}
        className="input-brand mb-3"
      />

      <textarea
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        placeholder={t("comment_placeholder")}
        maxLength={2000}
        rows={3}
        className="input-brand mb-3 resize-none"
      />

      {errorMessage && <p className="mb-3 text-xs text-brand-red">{errorMessage}</p>}

      <Button type="submit" variant="primary" size="sm" loading={isPending}>
        {t("submit")}
      </Button>
    </form>
  );
}

"use client";

import { useEffect, useId, useMemo, useRef, useState, type ChangeEvent, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { ImagePlus, Star, X } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { useAuthStore } from "@/store/authStore";
import { useCreateReview } from "@/lib/hooks/useCreateReview";
import { useProducts } from "@/lib/hooks/useProducts";
import type { Product } from "@/lib/types";
import {
  MAX_BYTES_DE_FOTO,
  MAX_BYTES_ORIGINAL,
  TIPOS_DE_FOTO,
  comprimirImagen,
} from "@/lib/utils/comprimirImagen";

const MAX_FOTOS = 3;

interface FotoElegida {
  id: string;
  file: File;
  preview: string;
}

/**
 * Hasta 3 fotos con vista previa y botón para quitar cada una. Se comprimen al
 * elegirlas (no al enviar), así el envío no espera y los errores salen enseguida.
 */
function PhotoPicker({
  fotos,
  onChange,
  onError,
  disabled,
}: {
  fotos: FotoElegida[];
  onChange: (fotos: FotoElegida[]) => void;
  onError: (mensaje: string | null) => void;
  disabled: boolean;
}) {
  const t = useTranslations("reviews");
  const inputRef = useRef<HTMLInputElement>(null);
  const hintId = useId();
  const [preparando, setPreparando] = useState(false);
  const llenas = fotos.length >= MAX_FOTOS;

  const elegir = async (e: ChangeEvent<HTMLInputElement>) => {
    const archivos = Array.from(e.target.files ?? []);
    e.target.value = "";
    if (archivos.length === 0) return;

    onError(null);
    const libres = MAX_FOTOS - fotos.length;
    const errores: string[] = [];
    if (archivos.length > libres) errores.push(t("photo_limit_error", { max: MAX_FOTOS }));

    setPreparando(true);
    const nuevas: FotoElegida[] = [];
    for (const archivo of archivos.slice(0, libres)) {
      if (!(TIPOS_DE_FOTO as readonly string[]).includes(archivo.type)) {
        errores.push(t("photo_type_error", { name: archivo.name }));
        continue;
      }
      if (archivo.size > MAX_BYTES_ORIGINAL) {
        errores.push(t("photo_size_error", { name: archivo.name }));
        continue;
      }
      try {
        const comprimida = await comprimirImagen(archivo, fotos.length + nuevas.length);
        if (comprimida.size > MAX_BYTES_DE_FOTO) {
          errores.push(t("photo_size_error", { name: archivo.name }));
          continue;
        }
        nuevas.push({
          id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
          file: comprimida,
          preview: URL.createObjectURL(comprimida),
        });
      } catch {
        errores.push(t("photo_read_error", { name: archivo.name }));
      }
    }
    setPreparando(false);

    if (nuevas.length > 0) onChange([...fotos, ...nuevas]);
    if (errores.length > 0) onError(errores.join(" "));
  };

  const quitar = (id: string) => {
    const foto = fotos.find((f) => f.id === id);
    if (foto) URL.revokeObjectURL(foto.preview);
    onChange(fotos.filter((f) => f.id !== id));
    inputRef.current?.focus();
  };

  return (
    <div className="mb-3">
      <div className="flex flex-wrap items-center gap-3">
        {fotos.map((foto, i) => (
          <div key={foto.id} className="relative h-20 w-20 overflow-hidden rounded-lg border border-white/10 bg-black">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={foto.preview} alt={t("photo_preview_alt", { n: i + 1 })} className="h-full w-full object-cover" />
            <button
              type="button"
              onClick={() => quitar(foto.id)}
              disabled={disabled}
              aria-label={t("remove_photo", { n: i + 1 })}
              className="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/75 text-white transition hover:bg-brand-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold"
            >
              <X size={14} aria-hidden />
            </button>
          </div>
        ))}

        {!llenas && (
          <label
            className={`flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-white/20 text-center text-[11px] leading-tight text-brand-muted transition hover:border-brand-gold hover:text-brand-white focus-within:border-brand-gold ${
              disabled || preparando ? "pointer-events-none opacity-50" : ""
            }`}
          >
            <ImagePlus size={20} aria-hidden />
            <span>{preparando ? t("preparing_photos") : t("add_photos")}</span>
            <input
              ref={inputRef}
              type="file"
              accept={TIPOS_DE_FOTO.join(",")}
              multiple
              onChange={elegir}
              disabled={disabled || preparando}
              aria-describedby={hintId}
              className="sr-only"
            />
          </label>
        )}
      </div>
      <p id={hintId} className="mt-2 text-xs text-brand-muted">
        {t("photos_hint", { count: fotos.length, max: MAX_FOTOS })}
      </p>
    </div>
  );
}

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
  const [fotos, setFotos] = useState<FotoElegida[]>([]);
  const [submitted, setSubmitted] = useState<"plain" | "photos" | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Libera las vistas previas al salir. Se lee la lista vigente con una ref para no
  // revocar las que siguen en pantalla en cada cambio.
  const fotosRef = useRef(fotos);
  fotosRef.current = fotos;
  useEffect(() => () => fotosRef.current.forEach((f) => URL.revokeObjectURL(f.preview)), []);

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
        {submitted === "photos" ? t("success_message_photos") : t("success_message")}
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
        photos: fotos.map((f) => f.file),
      },
      {
        onSuccess: () => {
          fotos.forEach((f) => URL.revokeObjectURL(f.preview));
          setFotos([]);
          setSubmitted(fotos.length > 0 ? "photos" : "plain");
        },
        onError: (err) => {
          // 'code' es machine-readable (backend); si viene, mostramos nuestra
          // propia traducción en vez del 'message' fijo en español.
          const response = (
            err as {
              response?: { status?: number; data?: { message?: string; code?: string; errors?: Record<string, string[]> } };
            }
          ).response;
          const errorDeFotos = Object.keys(response?.data?.errors ?? {}).some((campo) => campo.startsWith("photos"));
          const msg =
            response?.data?.code === "already_reviewed"
              ? t("already_reviewed")
              : response?.status === 429
                ? t("too_many_requests")
                : errorDeFotos
                  ? t("photos_rejected")
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

      <PhotoPicker fotos={fotos} onChange={setFotos} onError={setErrorMessage} disabled={isPending} />

      {errorMessage && (
        <p role="alert" className="mb-3 text-xs text-brand-red">
          {errorMessage}
        </p>
      )}

      <Button type="submit" variant="primary" size="sm" loading={isPending}>
        {t("submit")}
      </Button>
    </form>
  );
}

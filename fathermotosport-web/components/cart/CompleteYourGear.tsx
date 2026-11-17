"use client";

import { useMemo } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { Plus } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { useCartStore } from "@/store/cartStore";
import { useAllProducts } from "@/lib/hooks/useAllProducts";
import { trackAddToCart } from "@/lib/analytics/events";
import { ga4Event } from "@/lib/analytics/ga4";
import { finalPrice, formatPrice, getImageUrl } from "@/lib/utils";
import type { Product } from "@/lib/types";
import { QuickSizePicker, needsSizeChoice } from "@/components/product/QuickSizePicker";
import { useState } from "react";

/**
 * Qué completa a qué (por slug de categoría), en orden de prioridad. Un casco pide guantes;
 * unos guantes piden casco; etc. Lo que no esté en el mapa cae en accesorios y guantes.
 */
const COMPLEMENTOS: Record<string, string[]> = {
  cascos: ["guantes", "accesorios", "chaquetas"],
  guantes: ["cascos", "chaquetas", "botas"],
  botas: ["guantes", "chaquetas", "accesorios"],
  chaquetas: ["guantes", "botas", "cascos"],
  accesorios: ["guantes", "cascos"],
  "breathe-divinity": ["accesorios", "guantes"],
  repuestos: ["accesorios", "guantes"],
};
const POR_DEFECTO = ["accesorios", "guantes"];

/** Variantes que se pueden comprar ahora. */
function disponibles(p: Product) {
  return (p.variants ?? []).filter((v) => v.is_active && v.stock > 0);
}

/** Popular/destacado y con descuento primero: lo que más probablemente se sume. */
function puntaje(p: Product): number {
  return (p.is_popular ? 4 : 0) + (p.is_featured ? 2 : 0) + (Number(p.discount_percent) > 0 ? 3 : 0) + (p.is_new ? 1 : 0);
}

export function recomendar(catalogo: Product[], enCarrito: Set<string>, limite: number): Product[] {
  const porId = new Map(catalogo.map((p) => [String(p.id), p]));
  const categoriasCarrito = [...enCarrito].map((id) => porId.get(id)?.category?.slug).filter(Boolean) as string[];
  if (!categoriasCarrito.length) return [];

  // Categorías complementarias, intercaladas por prioridad, sin repetir las del carrito.
  const listas = categoriasCarrito.map((c) => COMPLEMENTOS[c] ?? POR_DEFECTO);
  const objetivo: string[] = [];
  for (let i = 0; i < 3; i++) {
    for (const l of listas) {
      const c = l[i];
      if (c && !objetivo.includes(c) && !categoriasCarrito.includes(c)) objetivo.push(c);
    }
  }
  if (!objetivo.length) objetivo.push(...POR_DEFECTO.filter((c) => !categoriasCarrito.includes(c)));

  const elegidos: Product[] = [];
  const porCategoria = Math.max(1, Math.ceil(limite / Math.min(objetivo.length, limite)));
  for (const slug of objetivo) {
    const candidatos = catalogo
      .filter((p) => p.category?.slug === slug && !enCarrito.has(String(p.id)) && disponibles(p).length > 0)
      .sort((a, b) => puntaje(b) - puntaje(a));
    elegidos.push(...candidatos.slice(0, porCategoria));
    if (elegidos.length >= limite) break;
  }
  return elegidos.slice(0, limite);
}

export function CompleteYourGear({ limit = 4, compact = false, origen }: { limit?: number; compact?: boolean; origen: string }) {
  const t = useTranslations("cross_sell");
  const items = useCartStore((s) => s.items);
  const addItem = useCartStore((s) => s.addItem);
  const { data: catalogo } = useAllProducts(items.length > 0);
  const [eligiendo, setEligiendo] = useState<Product | null>(null);

  const enCarrito = useMemo(() => new Set(items.map((i) => String(i.productId))), [items]);
  const sugeridos = useMemo(() => recomendar(catalogo ?? [], enCarrito, limit), [catalogo, enCarrito, limit]);

  if (!sugeridos.length) return null;

  const agregar = (p: Product) => {
    const variante = disponibles(p)[0];
    if (!variante) return;
    addItem(p, variante, 1);
    trackAddToCart(p, 1);
    ga4Event("cross_sell_add", { origen, item_id: p.id, item_name: p.name });
    toast.success(t("added", { name: p.name }));
  };

  return (
    <section aria-label={t("title")} className={compact ? "mt-4" : "mt-8"}>
      <div className="mb-3 flex items-baseline justify-between gap-2">
        <h3 className={`font-extrabold uppercase tracking-wide text-brand-white ${compact ? "text-xs" : "text-sm"}`}>{t("title")}</h3>
        {!compact && <span className="text-[11px] text-brand-muted">{t("subtitle")}</span>}
      </div>
      <div className={compact ? "space-y-2" : "-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0"}>
        {sugeridos.map((p) => {
          const vars = disponibles(p);
          // Con una sola opción (o sin talla) se agrega directo; con tallas, a elegir en la ficha.
          const directo = !needsSizeChoice(p) && vars.length > 0;
          const precio = finalPrice(p);
          const lista = Number(p.price);
          const rebajado = Number(p.discount_percent) > 0 && lista > precio;

          return (
            <div
              key={p.id}
              className={`group overflow-hidden rounded-xl border border-white/10 bg-brand-card transition hover:border-brand-gold/40 ${
                compact ? "flex items-center gap-3 p-2" : "flex w-40 shrink-0 snap-start flex-col sm:w-auto"
              }`}
            >
              <Link
                href={`/product/${p.slug}`}
                onClick={() => ga4Event("cross_sell_click", { origen, item_id: p.id })}
                className={`relative shrink-0 overflow-hidden bg-white ${compact ? "h-14 w-14 rounded-lg" : "aspect-square w-full"}`}
              >
                <Image
                  src={getImageUrl(p.primary_image)}
                  alt={p.name}
                  fill
                  sizes={compact ? "56px" : "(max-width: 640px) 50vw, 200px"}
                  className="object-contain p-1 transition group-hover:scale-105"
                />
                {rebajado && !compact && (
                  <span className="absolute left-1.5 top-1.5 rounded-full bg-brand-red px-2 py-0.5 text-[10px] font-black text-white">
                    -{Number(p.discount_percent)}%
                  </span>
                )}
              </Link>
              <div className={`flex min-w-0 flex-1 flex-col ${compact ? "" : "p-3"}`}>
                <Link href={`/product/${p.slug}`} className="line-clamp-2 text-xs font-semibold leading-snug text-brand-white hover:text-brand-gold">
                  {p.name}
                </Link>
                <div className="mt-1 flex items-center justify-between gap-2">
                  <span className="text-sm font-bold text-brand-white">
                    {formatPrice(precio)}
                    {rebajado && <span className="ml-1 text-[10px] font-normal text-brand-muted line-through">{formatPrice(lista)}</span>}
                  </span>
                  {directo ? (
                    <button
                      type="button"
                      onClick={() => agregar(p)}
                      aria-label={t("add_aria", { name: p.name })}
                      className="flex h-8 shrink-0 items-center gap-1 rounded-lg bg-brand-red px-2.5 text-[11px] font-bold text-white transition hover:brightness-110"
                    >
                      <Plus size={14} />
                      {!compact && t("add")}
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        ga4Event("cross_sell_click", { origen, item_id: p.id });
                        setEligiendo(p);
                      }}
                      className="shrink-0 rounded-lg border border-brand-gold/40 px-2.5 py-1.5 text-[11px] font-bold text-brand-gold hover:bg-brand-gold/10"
                    >
                      {t("choose_size")}
                    </button>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
      {eligiendo && <QuickSizePicker product={eligiendo} open onClose={() => setEligiendo(null)} origen={origen} />}
    </section>
  );
}

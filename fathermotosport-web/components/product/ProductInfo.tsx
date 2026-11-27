"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Heart, Minus, Plus, Ruler, ShieldCheck, Star, Truck, MessageCircle, Award } from "lucide-react";

/** Precio (USD) desde el que el producto se vende con factura de originalidad. */
const ORIGINAL_CON_FACTURA_DESDE = 1000;

/**
 * Originalidad comprobada (decisión de Gerson, 27-nov-2026): el resto del catálogo es
 * dropshipping desde China y no se afirma que sea original. Solo los productos de USD 1.000
 * o más (con factura) y los cascos Ruroc.
 */
function esOriginalComprobado(product: Parameters<typeof finalPrice>[0] & {
  brand?: { slug?: string; name?: string } | null;
  category?: { slug?: string } | null;
}): boolean {
  const marca = (product.brand?.slug ?? product.brand?.name ?? "").toLowerCase();
  return finalPrice(product) >= ORIGINAL_CON_FACTURA_DESDE || (marca === "ruroc" && isHelmet(product));
}
import type { Product, ProductVariant } from "@/lib/types";
import Image from "next/image";
import { cn, finalPrice, formatDiscount, formatPrice, getCategoryColor, getImageUrl, hasDiscount, isHelmet } from "@/lib/utils";
import { useVisorColors } from "@/lib/hooks/useVisorColors";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { SizeGuideModal, hasSizeGuide } from "@/components/product/SizeGuideModal";
import { ShippingReturns } from "@/components/product/ShippingReturns";
import { StockAlertForm } from "@/components/product/StockAlertForm";
import { REVIEWS_ANCHOR, SHOW_REVIEWS_EVENT } from "@/components/product/ProductTabs";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";
import type { ShippingReturnsSummary } from "@/lib/shippingReturns";
import { whatsappUrl } from "@/lib/api/storeSettings";
import { sortSizes } from "@/lib/utils/sizes";
import { variantImage } from "@/lib/utils/variantImage";
import { useCartStore } from "@/store/cartStore";
import { useFavoritesStore } from "@/store/favoritesStore";
import { useAuthStore } from "@/store/authStore";
import toast from "react-hot-toast";
import { trackAddToCart, trackViewContent, trackWhatsappClick } from "@/lib/analytics/events";

export function ProductInfo({
  product,
  shippingReturns,
  onOptionChange,
}: {
  product: Product;
  shippingReturns: ShippingReturnsSummary;
  /** Avisa a la galería qué opción (talla/color/modelo) está elegida para mostrar su foto. */
  onOptionChange?: (option: string | null) => void;
}) {
  const t = useTranslations("product");
  const locale = useLocale();
  const tWhatsapp = useTranslations("whatsapp");
  const tReviews = useTranslations("reviews");
  const accent = getCategoryColor(product.category?.slug);

  const { whatsappDigits } = useStoreSettings();
  const whatsappHref = whatsappUrl(whatsappDigits, tWhatsapp("product_message", { name: product.name }));
  const variants = useMemo(() => (product.variants ?? []).filter((v) => v.is_active), [product]);

  const sizes = useMemo(
    () => sortSizes([...new Set(variants.map((v) => v.size).filter((s): s is string => !!s))]),
    [variants]
  );
  // Si las opciones son tallas (S, M, XL, 2XL, 58, 42.5…) se titula "Talla"; si son
  // nombres (Azul, Dragón Azul, Fénix Amarillo…) se titula "Diseño".
  const optionsAreSizes = useMemo(
    () => sizes.some((s) => /^(\d*X{0,3}[SML]|\d{1,3}([.,]\d)?|[UÚ](NICA)?|ONE SIZE)$/i.test(s.trim())),
    [sizes]
  );
  // Selección inicial: la primera talla (en orden) que tenga stock.
  const [size, setSize] = useState<string | null>(
    () => sizes.find((s) => variants.some((v) => v.size === s && v.stock > 0)) ?? sizes[0] ?? null
  );
  const [qty, setQty] = useState(1);
  // ¿Eligió la talla a mano? La barra fija no agrega una talla que el cliente no vio.
  const [sizeTouched, setSizeTouched] = useState(false);
  const [sizeGuideOpen, setSizeGuideOpen] = useState(false);

  // Cascos: el cliente elige el color de visor y viaja con el pedido. Los visores
  // propios del producto; si no tiene, el catálogo global (igual que «Ver visores»).
  const helmet = isHelmet(product);
  const ownVisors = product.visor_colors ?? [];
  const { data: globalVisors } = useVisorColors(helmet && ownVisors.length === 0);
  const visors = helmet ? (ownVisors.length > 0 ? ownVisors : globalVisors ?? []) : [];
  const [visorId, setVisorId] = useState<string | null>(null);
  const selectedVisor = visors.find((v) => v.id === visorId) ?? null;
  const needsVisor = visors.length > 0;

  // El color es del producto, así que la variante depende solo de la talla.
  // Productos sin talla (ej. viseras): la primera variante con stock.
  const selectedVariant: ProductVariant | undefined =
    sizes.length > 0
      ? variants.find((v) => v.size === size)
      : variants.find((v) => v.stock > 0) ?? variants[0];

  // Una vista de ficha por producto (no por cada cambio de talla).
  useEffect(() => {
    trackViewContent(product);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [product.id]);

  useEffect(() => {
    onOptionChange?.(selectedVariant?.size ?? null);
  }, [selectedVariant?.size, onOptionChange]);

  const selectSize = (s: string) => {
    setSize(s);
    setQty(1);
    setSizeTouched(true);
  };

  // Barra fija de compra (solo celular): aparece cuando el botón principal sale de pantalla.
  const ctaRef = useRef<HTMLDivElement>(null);
  const sizesRef = useRef<HTMLDivElement>(null);
  const [ctaVisible, setCtaVisible] = useState(true);
  useEffect(() => {
    const el = ctaRef.current;
    if (!el || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(([e]) => setCtaVisible(e.isIntersecting), { threshold: 0 });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  const addItem = useCartStore((s) => s.addItem);
  const toggleFav = useFavoritesStore((s) => s.toggle);
  const isFav = useFavoritesStore((s) => s.isFavorite(product.id));
  const isAuth = useAuthStore((s) => s.isAuth);

  // Stock a mostrar: si hay variante seleccionada, su stock; si no, el total
  // de todas las variantes activas (variants ya viene filtrado por is_active).
  const totalStock = variants.reduce((sum, v) => sum + v.stock, 0);
  const displayStock = selectedVariant ? selectedVariant.stock : totalStock;
  const inStock = displayStock > 0;
  const canAdd = !!selectedVariant && selectedVariant.stock > 0;
  // Agotado en todas las tallas: en vez de «Agregar al carrito» va «Avísame».
  const soldOut = totalStock <= 0;
  const rating = Math.round(product.rating_avg ?? 0);

  const handleAdd = () => {
    if (!selectedVariant || !canAdd) {
      toast.error(t("no_stock"));
      return;
    }
    if (needsVisor && !selectedVisor) {
      toast.error(t("visor_required"));
      document.getElementById("visor-picker")?.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }
    // La foto de la opción elegida es la que viaja al carrito y al checkout.
    addItem(
      product,
      selectedVariant,
      qty,
      variantImage(product.images, selectedVariant.size)?.url,
      selectedVisor ? { id: selectedVisor.id, name: selectedVisor.name } : null
    );
    trackAddToCart(product, qty);
    toast.success(`${product.name} agregado al carrito`);
  };

  return (
    <div className="space-y-5">
      <div>
        <p translate="no" className="text-sm font-semibold uppercase tracking-wide" style={{ color: accent }}>
          {product.brand?.name}
        </p>
        <h1 className="mt-1 text-3xl font-extrabold text-brand-white">{product.name}</h1>
        {product.color && (
          <p className="mt-1 text-sm text-brand-muted">
            {t("color")}: <span className="text-brand-white">{product.color}</span>
          </p>
        )}

        <div className="mt-2 flex items-center gap-3">
          <div className="flex">
            {Array.from({ length: 5 }).map((_, i) => (
              <Star
                key={i}
                size={16}
                className={i < rating ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
              />
            ))}
          </div>
          {/* Lleva a la pestaña de reseñas de ProductTabs (la abre y baja hasta ella). */}
          <a
            href={`#${REVIEWS_ANCHOR}`}
            onClick={(e) => {
              e.preventDefault();
              window.history.replaceState(null, "", `#${REVIEWS_ANCHOR}`);
              window.dispatchEvent(new Event(SHOW_REVIEWS_EVENT));
            }}
            className="text-xs text-brand-muted hover:text-brand-white hover:underline"
          >
            {tReviews("count", { count: product.reviews_count ?? 0 })}
          </a>
          {inStock ? (
            <Badge variant="green">{t("in_stock")}</Badge>
          ) : (
            <Badge variant="red">{t("out_of_stock")}</Badge>
          )}
        </div>
      </div>

      <div className="flex items-baseline gap-3">
        <span className="text-3xl font-extrabold text-brand-white">
          {formatPrice(finalPrice(product))}
        </span>
        {hasDiscount(product) && (
          <>
            <span className="text-lg text-brand-muted line-through">{formatPrice(product.price)}</span>
            {product.discount_percent > 0 && (
              <Badge variant="red">{formatDiscount(product.discount_percent, locale)}</Badge>
            )}
          </>
        )}
      </div>

      {/* Originalidad solo donde está comprobada (ver esOriginalComprobado). */}
      {esOriginalComprobado(product) && (
        <p className="-mt-2 flex items-center gap-1.5 text-sm font-semibold text-brand-gold">
          <ShieldCheck size={16} aria-hidden /> {t("original_invoice")}
        </p>
      )}

      {sizes.length > 0 && (
        <div ref={sizesRef} className="scroll-mt-24">
          <p className="mb-2 text-sm font-semibold text-brand-white">{optionsAreSizes ? t("size") : t("design")}</p>
          <div className="flex flex-wrap gap-2">
            {sizes.map((s) => {
              const disabled = !variants.some((x) => x.size === s && x.stock > 0);
              return (
                <button
                  key={s}
                  onClick={() => selectSize(s)}
                  disabled={disabled}
                  className={cn(
                    "min-w-[44px] rounded-lg border px-3 py-2 text-sm font-medium transition",
                    size === s
                      ? "border-brand-red bg-brand-red/10 text-brand-white"
                      : "border-white/10 text-brand-muted hover:border-white/30",
                    disabled && "cursor-not-allowed opacity-40 line-through"
                  )}
                >
                  {s}
                </button>
              );
            })}
          </div>
          {hasSizeGuide(product.category?.slug) && (
            <>
              <button
                onClick={() => setSizeGuideOpen(true)}
                className="mt-2 flex items-center gap-1.5 text-xs font-medium text-brand-muted transition hover:text-brand-white"
              >
                <Ruler size={14} />
                {t("size_guide")}
              </button>
              <SizeGuideModal
                open={sizeGuideOpen}
                onClose={() => setSizeGuideOpen(false)}
                categorySlug={product.category?.slug}
              />
            </>
          )}
        </div>
      )}

      {needsVisor && !soldOut && (
        <div id="visor-picker">
          <p className="mb-2 text-sm font-semibold text-brand-white">
            {t("visor_color")}
            {selectedVisor ? (
              <span className="font-normal text-brand-muted">: {selectedVisor.name}</span>
            ) : (
              <span className="ml-1 font-normal text-brand-red">· {t("visor_choose")}</span>
            )}
          </p>
          <div className="flex flex-wrap gap-3" role="radiogroup" aria-label={t("visor_color")}>
            {visors.map((v) => (
              <button
                key={v.id}
                type="button"
                role="radio"
                aria-checked={visorId === v.id}
                onClick={() => setVisorId(v.id)}
                className="flex w-16 flex-col items-center gap-1"
              >
                <span
                  className={cn(
                    "relative h-11 w-11 overflow-hidden rounded-full border-2 transition",
                    visorId === v.id ? "border-brand-red ring-2 ring-brand-red/40" : "border-white/20 hover:border-white/50"
                  )}
                  style={v.image_url ? undefined : { backgroundColor: v.hex_color }}
                >
                  {v.image_url && (
                    <Image src={getImageUrl(v.image_url)} alt={v.name} fill className="object-cover" sizes="44px" />
                  )}
                </span>
                <span
                  className={cn(
                    "line-clamp-2 text-center text-[11px] leading-tight",
                    visorId === v.id ? "font-semibold text-brand-white" : "text-brand-muted"
                  )}
                >
                  {v.name}
                </span>
              </button>
            ))}
          </div>
        </div>
      )}

      {soldOut && (
        <StockAlertForm slug={product.slug} sizes={sizes} sizeLabel={optionsAreSizes ? t("size") : t("design")} />
      )}

      {!soldOut && (
        <div className="flex items-center gap-4">
          <div className="flex items-center rounded-lg border border-white/10">
            <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="p-2.5 text-brand-muted hover:text-brand-white">
              <Minus size={16} />
            </button>
            <span className="min-w-[40px] text-center font-semibold text-brand-white">{qty}</span>
            <button
              onClick={() => setQty((q) => Math.min(selectedVariant?.stock ?? 99, q + 1))}
              className="p-2.5 text-brand-muted hover:text-brand-white"
            >
              <Plus size={16} />
            </button>
          </div>
          <span className="text-xs text-brand-muted">
            {displayStock} {t("available")}
          </span>
        </div>
      )}

      {(!soldOut || isAuth) && (
        <div ref={ctaRef} className="flex flex-col gap-3 sm:flex-row">
          {!soldOut && (
            <Button variant="primary" className="flex-1" onClick={handleAdd} disabled={!canAdd}>
              {t("add_to_cart")}
            </Button>
          )}
          {isAuth && (
            <Button
              variant="glass"
              icon={<Heart size={18} className={isFav ? "fill-brand-red text-brand-red" : ""} />}
              onClick={() => toggleFav(product)}
            >
              {isFav ? "Guardado" : "Favoritos"}
            </Button>
          )}
        </div>
      )}

      {!soldOut && (
        <div
          aria-hidden={ctaVisible}
          className={cn(
            "fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-brand-carbon/95 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur transition-transform duration-300 lg:hidden",
            ctaVisible ? "pointer-events-none translate-y-full" : "translate-y-0"
          )}
        >
          <div className="mx-auto flex max-w-7xl items-center gap-3">
            <div className="min-w-0 flex-1">
              <p className="truncate text-xs text-brand-muted">{product.name}</p>
              <p className="flex items-baseline gap-2">
                <span className="text-lg font-extrabold text-brand-white">{formatPrice(finalPrice(product))}</span>
                {hasDiscount(product) && (
                  <span className="text-xs text-brand-muted line-through">{formatPrice(product.price)}</span>
                )}
              </p>
            </div>
            {sizes.length > 1 && !sizeTouched ? (
              <Button
                variant="primary"
                tabIndex={ctaVisible ? -1 : 0}
                onClick={() => sizesRef.current?.scrollIntoView({ behavior: "smooth", block: "start" })}
              >
                {optionsAreSizes ? t("choose_size") : t("choose_design")}
              </Button>
            ) : needsVisor && !selectedVisor ? (
              // Cascos: sin color de visor no se puede agregar; la barra lleva al selector.
              <Button
                variant="primary"
                tabIndex={ctaVisible ? -1 : 0}
                onClick={() =>
                  document.getElementById("visor-picker")?.scrollIntoView({ behavior: "smooth", block: "center" })
                }
              >
                {t("visor_color")}
              </Button>
            ) : (
              <Button variant="primary" tabIndex={ctaVisible ? -1 : 0} onClick={handleAdd} disabled={!canAdd}>
                {t("add_to_cart")}
                {sizes.length > 1 && size ? ` · ${size}` : ""}
              </Button>
            )}
          </div>
        </div>
      )}

      <ShippingReturns content={shippingReturns} />

      {whatsappHref && (
        <a
          href={whatsappHref}
          target="_blank"
          rel="noopener noreferrer"
          onClick={() => trackWhatsappClick("product", product)}
        >
          <Button variant="glass" className="w-full" icon={<MessageCircle size={18} />}>
            {t("whatsapp")}
          </Button>
        </a>
      )}

      <div className="grid grid-cols-2 gap-3 border-t border-white/10 pt-5 sm:grid-cols-4">
        {[
          { icon: Truck, label: t("free_shipping") },
          { icon: ShieldCheck, label: t("warranty") },
          // Solo la certificación cargada para ESTE modelo: sin dato no se afirma ninguna.
          ...(product.certification ? [{ icon: Award, label: product.certification }] : []),
          { icon: MessageCircle, label: t("support") },
        ].map(({ icon: Icon, label }, i) => (
          <div key={i} className="flex flex-col items-center gap-1.5 text-center">
            <Icon size={20} style={{ color: accent }} />
            <span className="text-xs text-brand-muted">{label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

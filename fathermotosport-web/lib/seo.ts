import type { Metadata } from "next";
import type { PaginatedResponse, Post, Product } from "@/lib/types";
import { finalPrice } from "@/lib/utils";

/** Dominio público (con www: es al que redirige fathermotosport.com). */
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "https://www.fathermotosport.com").replace(/\/$/, "");
export const SITE_NAME = "FatherMotoSport";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/** Tag de caché de productos (lo puede invalidar Laravel por /api/revalidate). */
export const PRODUCTS_TAG = "products";

export const LOCALES = ["es", "pt", "en"] as const;
const DEFAULT_LOCALE = "es";
const OG_LOCALE: Record<string, string> = { es: "es_ES", pt: "pt_BR", en: "en_US" };

/** URL absoluta de una ruta en un idioma (localePrefix "as-needed": el español va sin prefijo). */
export function localeUrl(path: string, locale: string): string {
  const limpio = path === "/" ? "" : path.startsWith("/") ? path : `/${path}`;
  const prefijo = locale === DEFAULT_LOCALE ? "" : `/${locale}`;
  return `${SITE_URL}${prefijo}${limpio}` || SITE_URL;
}

/** Canonical + hreflang (es, pt, en y x-default) para una ruta. */
export function alternatesFor(path: string, locale: string): Metadata["alternates"] {
  return {
    canonical: localeUrl(path, locale),
    languages: {
      ...Object.fromEntries(LOCALES.map((l) => [l, localeUrl(path, l)])),
      "x-default": localeUrl(path, DEFAULT_LOCALE),
    },
  };
}

export function ogLocale(locale: string): string {
  return OG_LOCALE[locale] ?? OG_LOCALE.es;
}

/** Las URLs de storage traen espacios y acentos: se codifican para que WhatsApp y Google las lean. */
export function absoluteImage(url: string | null | undefined): string | undefined {
  if (!url) return undefined;
  try {
    return encodeURI(decodeURI(url));
  } catch {
    return url;
  }
}

/** Texto plano corto para descripciones (la descripción del producto viene en HTML). */
export function plainText(html: string | null | undefined, max = 160): string {
  if (!html) return "";
  const txt = html
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&[a-z]+;/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
  return txt.length > max ? `${txt.slice(0, max - 1).replace(/\s+\S*$/, "")}…` : txt;
}

/**
 * Producto leído en el servidor (fetch nativo, Data Cache de 10 min) solo para los
 * metadatos y el JSON-LD. La ficha en sí la sigue pintando ProductClient.
 */
export async function getProductServidor(slug: string): Promise<Product | null> {
  try {
    const res = await fetch(`${API_URL}/products/${encodeURIComponent(slug)}`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 600, tags: [PRODUCTS_TAG] },
    });
    if (!res.ok) return null;
    const json = (await res.json()) as { data?: Product };
    return json.data ?? null;
  } catch {
    return null;
  }
}

/** Todas las páginas de un listado paginado de la API (para el sitemap). */
async function todasLasPaginas<T>(path: string, extra = ""): Promise<T[]> {
  const out: T[] = [];
  for (let page = 1; page <= 50; page++) {
    try {
      const res = await fetch(`${API_URL}${path}?page=${page}${extra}`, {
        headers: { Accept: "application/json" },
        next: { revalidate: 3600, tags: [PRODUCTS_TAG] },
      });
      if (!res.ok) break;
      const json = (await res.json()) as PaginatedResponse<T>;
      out.push(...(json.data ?? []));
      if (!json.meta || page >= json.meta.last_page) break;
    } catch {
      break;
    }
  }
  return out;
}

export function getAllProductsServidor(): Promise<Product[]> {
  return todasLasPaginas<Product>("/products", "&per_page=60");
}

export function getAllPostsServidor(): Promise<Post[]> {
  return todasLasPaginas<Post>("/posts");
}

/** Datos estructurados schema.org/Product: precio, stock, marca y estrellas en Google. */
export function productJsonLd(product: Product, locale: string) {
  const url = localeUrl(`/product/${product.slug}`, locale);
  const imagenes = (product.images ?? [])
    .filter((i) => i.type !== "video" && !/\.(mp4|webm|mov)(\?|$)/i.test(i.url))
    .map((i) => (i.type === "gif" ? i.thumbnail_url ?? i.url : i.url))
    .map(absoluteImage)
    .filter(Boolean) as string[];
  if (imagenes.length === 0 && product.primary_image) imagenes.push(absoluteImage(product.primary_image)!);

  const stock = product.total_stock ?? (product.variants ?? []).filter((v) => v.is_active).reduce((s, v) => s + v.stock, 0);
  const enStock = product.in_stock ?? stock > 0;

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    sku: product.sku,
    ...(product.barcode ? { gtin: product.barcode } : {}),
    image: imagenes.slice(0, 6),
    description: plainText(product.short_description || product.description, 500) || product.name,
    ...(product.brand?.name ? { brand: { "@type": "Brand", name: product.brand.name } } : {}),
    ...(product.category?.name ? { category: product.category.name } : {}),
    offers: {
      "@type": "Offer",
      url,
      priceCurrency: "USD",
      price: finalPrice(product).toFixed(2),
      availability: enStock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
      itemCondition: "https://schema.org/NewCondition",
      seller: { "@type": "Organization", name: SITE_NAME },
    },
    ...(product.reviews_count && product.reviews_count > 0 && product.rating_avg
      ? {
          aggregateRating: {
            "@type": "AggregateRating",
            ratingValue: Number(product.rating_avg).toFixed(1),
            reviewCount: product.reviews_count,
          },
        }
      : {}),
  };
}

export function organizationJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "OnlineStore",
    name: SITE_NAME,
    url: SITE_URL,
    logo: `${SITE_URL}/icon.png`,
    sameAs: [
      "https://instagram.com/fathermotosport",
      "https://www.facebook.com/share/19AeLpZAUo/",
      "https://www.youtube.com/@FatherMotoSports",
      "https://www.tiktok.com/@fathersonmotosport",
    ],
  };
}

/** <script type="application/ld+json"> seguro (escapa "<" para que no cierre la etiqueta). */
export function jsonLdHtml(data: unknown): { __html: string } {
  return { __html: JSON.stringify(data).replace(/</g, "\\u003c") };
}

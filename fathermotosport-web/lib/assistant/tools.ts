import { ENABLED_PAYMENT_METHODS } from "@/lib/data/paymentMethods";
import type { AssistantLocale, ProductCard } from "./types";

/**
 * Herramientas del asistente. Solo leen la API pública de la tienda: precios, stock, tallas,
 * envío y políticas son los mismos que ve el cliente en la web. Nada se inventa aquí.
 */

const API_URL = process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

async function api<T>(path: string, query: Record<string, string | number | undefined> = {}): Promise<T | null> {
  const url = new URL(`${API_URL}${path}`);
  for (const [k, v] of Object.entries(query)) {
    if (v !== undefined && v !== "") url.searchParams.set(k, String(v));
  }
  try {
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      // El stock cambia: se lee fresco. Las políticas sí pueden cachearse unos minutos.
      cache: path.startsWith("/store/") ? "force-cache" : "no-store",
      next: path.startsWith("/store/") ? { revalidate: 300 } : undefined,
      signal: AbortSignal.timeout(8000),
    });
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

/** Minúsculas sin tildes ni signos, para comparar. */
export function normalize(text: string): string {
  return text
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/[^a-z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

/** Quita el plural simple (cascos → casco, guantes → guante, botas → bota). */
function singular(word: string): string {
  if (word.length > 4 && word.endsWith("es") && !word.endsWith("ses")) return word.slice(0, -1);
  if (word.length > 3 && word.endsWith("s")) return word.slice(0, -1);
  return word;
}

const STOPWORDS = new Set([
  "el", "la", "los", "las", "un", "una", "unos", "unas", "de", "del", "para", "por", "con", "sin", "que", "y", "o", "en", "a",
  "me", "mi", "tu", "su", "se", "es", "hay", "tienen", "tiene", "quiero", "busco", "necesito", "ver", "algun", "alguna",
  "tem", "uma", "uns", "umas", "do", "da", "dos", "das", "com", "sem", "quero", "procuro", "preciso", "voces",
  "the", "an", "of", "for", "with", "do", "you", "have", "i", "want", "need", "looking", "any", "some", "is", "are", "to",
  "hola", "ola", "hello", "hi", "buenas", "precio", "price", "preco", "cuanto", "cuesta", "quanto", "custa", "how", "much",
  "talla", "tamanho", "size", "disponible", "disponivel", "available", "stock", "estoque",
]);

/** Palabras que sirven para buscar en el catálogo. */
export function searchTokens(text: string): string[] {
  const seen = new Set<string>();
  for (const raw of normalize(text).split(" ")) {
    if (raw.length < 2 || STOPWORDS.has(raw)) continue;
    seen.add(singular(raw));
  }
  return [...seen].slice(0, 5);
}

interface ApiVariant { id: string; size: string | null; finish: string | null; stock: number; is_active: boolean }
interface ApiProduct {
  name: string;
  slug: string;
  color?: string | null;
  short_description?: string | null;
  description?: string | null;
  price: string;
  final_price?: string | null;
  sale_price?: string | null;
  discount_percent?: number;
  in_stock?: boolean;
  weight?: string | null;
  specs?: Record<string, string> | null;
  certification?: string | null;
  primary_image?: string | null;
  brand?: { name: string } | null;
  category?: { name: string } | null;
  variants?: ApiVariant[];
}

function priceOf(p: ApiProduct): { price: number; list: number | null } {
  const list = parseFloat(p.price);
  const fin = parseFloat(p.final_price ?? p.sale_price ?? p.price);
  const price = Number.isFinite(fin) ? fin : list;
  return { price, list: price < list ? list : null };
}

function sizesInStock(p: ApiProduct): string[] {
  const out: string[] = [];
  for (const v of p.variants ?? []) {
    if (!v.is_active || v.stock <= 0) continue;
    const label = [v.size, v.finish].filter(Boolean).join(" ");
    if (label && !out.includes(label)) out.push(label);
  }
  return out;
}

export function toCard(p: ApiProduct): ProductCard {
  const { price, list } = priceOf(p);
  return {
    name: p.name.replace(/\s+/g, " ").trim(),
    slug: p.slug,
    brand: p.brand?.name ?? null,
    price,
    listPrice: list,
    discountPercent: list ? Math.round(((list - price) / list) * 100) : 0,
    inStock: p.in_stock ?? sizesInStock(p).length > 0,
    sizes: sizesInStock(p),
    image: p.primary_image ?? null,
  };
}

/** Busca en el catálogo por palabras sueltas y ordena por coincidencia, con stock primero. */
export async function searchProducts(
  query: string,
  opts: { maxPrice?: number; limit?: number } = {},
): Promise<ProductCard[]> {
  const tokens = searchTokens(query);
  if (tokens.length === 0) return [];
  const limit = Math.min(opts.limit ?? 5, 8);

  // La API busca la frase completa; se pide por palabra y se cruzan los resultados.
  const lists = await Promise.all(
    tokens.map((t) => api<{ data: ApiProduct[] }>("/products", { search: t, per_page: 60 })),
  );
  const byslug = new Map<string, { p: ApiProduct; score: number }>();
  for (const list of lists) {
    for (const p of list?.data ?? []) {
      if (!byslug.has(p.slug)) byslug.set(p.slug, { p, score: 0 });
    }
  }
  for (const entry of byslug.values()) {
    const hay = normalize(
      [entry.p.name, entry.p.brand?.name, entry.p.category?.name, entry.p.color, entry.p.short_description].join(" "),
    );
    entry.score = tokens.filter((t) => hay.includes(t)).length;
  }

  const all = [...byslug.values()].filter((e) => e.score > 0);
  // Si hay coincidencias fuertes (varias palabras), se descartan las que solo coinciden en una.
  const top = Math.max(0, ...all.map((e) => e.score));
  return all
    .filter((e) => (top >= 2 ? e.score === top : true))
    .filter((e) => (opts.maxPrice ? priceOf(e.p).price <= opts.maxPrice : true))
    .sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score;
      const sa = a.p.in_stock ? 1 : 0;
      const sb = b.p.in_stock ? 1 : 0;
      if (sb !== sa) return sb - sa;
      return priceOf(a.p).price - priceOf(b.p).price;
    })
    .slice(0, limit)
    .map((e) => toCard(e.p));
}

export interface ProductDetail extends ProductCard {
  description: string;
  specs: Record<string, string> | null;
  certification: string | null;
  weightKg: number | null;
  stockBySize: { size: string; stock: number }[];
  variantId: string | null;
}

function plain(html: string | null | undefined, max = 700): string {
  const text = (html ?? "").replace(/<[^>]*>/g, " ").replace(/&nbsp;/g, " ").replace(/\s+/g, " ").trim();
  return text.length > max ? `${text.slice(0, max)}…` : text;
}

export async function getProduct(slug: string): Promise<ProductDetail | null> {
  const res = await api<{ data: ApiProduct }>(`/products/${encodeURIComponent(slug)}`);
  const p = res?.data;
  if (!p) return null;
  const active = (p.variants ?? []).filter((v) => v.is_active);
  const weight = p.weight ? parseFloat(p.weight) : NaN;
  return {
    ...toCard(p),
    description: plain(p.description || p.short_description),
    specs: p.specs ?? null,
    certification: p.certification ?? null,
    weightKg: Number.isFinite(weight) && weight > 0 ? weight : null,
    stockBySize: active.map((v) => ({ size: [v.size, v.finish].filter(Boolean).join(" ") || "única", stock: v.stock })),
    variantId: active.find((v) => v.stock > 0)?.id ?? active[0]?.id ?? null,
  };
}

export interface ShippingResult {
  available: boolean;
  country: string;
  options: { method: string; price: string; currency: string; daysMin: number | null; daysMax: number | null }[];
  note?: string;
}

/** Opciones de envío reales (las mismas del carrito) para un país y un producto. */
export async function shippingOptions(countryCode: string, productSlug: string): Promise<ShippingResult> {
  const code = countryCode.trim().toUpperCase();
  const product = await getProduct(productSlug);
  if (!product?.variantId) {
    return { available: false, country: code, options: [], note: "No pude calcular el peso del producto." };
  }
  const res = await api<{
    available: boolean;
    country_name?: string;
    options: { method_name: string; price: string; currency: string; estimated_days_min: number | null; estimated_days_max: number | null }[];
    message?: string;
  }>("/shipping/calculate", { country_code: code, items: `${product.variantId}:1` });
  if (!res) return { available: false, country: code, options: [], note: "El cálculo de envío no respondió." };
  return {
    available: res.available,
    country: res.country_name ?? code,
    options: (res.options ?? []).map((o) => ({
      method: o.method_name,
      price: o.price,
      currency: o.currency,
      daysMin: o.estimated_days_min,
      daysMax: o.estimated_days_max,
    })),
    note: res.available ? undefined : res.message,
  };
}

export interface StoreInfo {
  returnsSummary: string | null;
  damageReportHours: number | null;
  whatsappAvailable: boolean;
  paymentMethods: string[];
  paymentNote: string;
}

const PAYMENT_LABEL: Record<AssistantLocale, Record<string, string>> = {
  es: { paypal: "PayPal", stripe: "tarjeta de crédito o débito" },
  pt: { paypal: "PayPal", stripe: "cartão de crédito ou débito" },
  en: { paypal: "PayPal", stripe: "credit or debit card" },
};

/** Políticas y formas de pago que la tienda tiene configuradas hoy. */
export async function storeInfo(locale: AssistantLocale): Promise<StoreInfo> {
  const res = await api<{
    data: { damage_report_hours: number | null; texts: { summary: string | null }; contact: { whatsapp: string | null } };
  }>("/store/shipping-returns", { locale });
  const hours = res?.data?.damage_report_hours ?? null;
  // Los textos del panel pueden llevar {hours}: se reemplaza por el plazo real.
  const summary = res?.data?.texts?.summary ?? null;
  return {
    returnsSummary: !summary
      ? null
      : summary.includes("{hours}")
        ? hours !== null
          ? summary.split("{hours}").join(String(hours))
          : null
        : summary,
    damageReportHours: hours,
    whatsappAvailable: Boolean(res?.data?.contact?.whatsapp),
    paymentMethods: ENABLED_PAYMENT_METHODS.map((m) => PAYMENT_LABEL[locale][m] ?? m),
    paymentNote: "Otros métodos de pago se coordinan con un asesor por WhatsApp.",
  };
}

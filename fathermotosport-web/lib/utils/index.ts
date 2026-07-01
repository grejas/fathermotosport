import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import type { OrderStatus } from "@/lib/types";

/** Combina clases de Tailwind resolviendo conflictos. */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

/**
 * Garantiza una URL de imagen absoluta para next/image.
 * - null/undefined → placeholder local
 * - URL absoluta (http) → se devuelve igual
 * - ruta que empieza con "/" → ruta local del frontend
 * - ruta relativa ("products/x.jpg") → la convierte a {API_HOST}/storage/products/x.jpg
 */
export function getImageUrl(url: string | null | undefined): string {
  if (!url) return "/images/placeholder-product.png";
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  if (url.startsWith("/")) return url;
  const apiHost = (process.env.NEXT_PUBLIC_API_URL ?? "").replace("/api/v1", "");
  return `${apiHost}/storage/${url.replace(/^storage\//, "")}`;
}

/** Formatea un precio en la moneda indicada (USD por defecto). */
export function formatPrice(price: string | number, currency: "USD" | "BOB" | "BRL" = "USD"): string {
  const value = typeof price === "string" ? parseFloat(price) : price;
  const locales: Record<string, string> = { USD: "en-US", BOB: "es-BO", BRL: "pt-BR" };
  try {
    return new Intl.NumberFormat(locales[currency] ?? "en-US", {
      style: "currency",
      currency,
      minimumFractionDigits: 2,
    }).format(isNaN(value) ? 0 : value);
  } catch {
    return `$${(isNaN(value) ? 0 : value).toFixed(2)}`;
  }
}

/** Formatea una fecha ISO a texto en español. */
export function formatDate(dateString: string): string {
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return "";
  return new Intl.DateTimeFormat("es-ES", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
}

/** Calcula el porcentaje de descuento entre precio y precio de oferta. */
export function getDiscountPercent(price: string | number, salePrice?: string | number | null): number {
  const p = typeof price === "string" ? parseFloat(price) : price;
  const s = salePrice == null ? null : typeof salePrice === "string" ? parseFloat(salePrice) : salePrice;
  if (!s || !p || s >= p) return 0;
  return Math.round(((p - s) / p) * 100);
}

/** Color hex de acento por categoría (según su slug). */
export function getCategoryColor(slug?: string): string {
  const map: Record<string, string> = {
    cascos: "#E8001D",
    guantes: "#6C63FF",
    botas: "#00C897",
    chamarras: "#FF7A00",
    viseras: "#00B4D8",
    repuestos: "#C9A84C",
    accesorios: "#00B4D8",
  };
  return (slug && map[slug]) || "#E8001D";
}

/** Etiquetas en español de los estados de pedido. */
export const orderStatusLabels: Record<OrderStatus, string> = {
  pending: "Pendiente",
  processing: "En preparación",
  shipped: "Enviado",
  delivered: "Entregado",
  cancelled: "Cancelado",
};

/** Color del badge por estado de pedido. */
export const orderStatusColors: Record<OrderStatus, string> = {
  pending: "text-brand-gold",
  processing: "text-cat-accs",
  shipped: "text-cat-jackets",
  delivered: "text-cat-boots",
  cancelled: "text-brand-red",
};

/** Trunca un texto a un máximo de caracteres. */
export function truncate(text: string, maxLength: number): string {
  if (!text) return "";
  return text.length > maxLength ? text.slice(0, maxLength).trimEnd() + "…" : text;
}

/** Devuelve el precio vigente (sale_price si existe). */
export function currentPrice(price: string, salePrice?: string | null): number {
  return parseFloat(salePrice ?? price);
}

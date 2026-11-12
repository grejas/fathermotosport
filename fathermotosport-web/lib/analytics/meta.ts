/**
 * Píxel de Meta (Facebook / Instagram): capa baja. Habla con `window.fbq`, que carga
 * <AnalyticsScripts /> en el layout.
 *
 * Sin NEXT_PUBLIC_META_PIXEL_ID (o con un valor inválido) el píxel no se carga y estas
 * funciones no hacen nada. Registrar un evento nunca debe romper la navegación ni la compra.
 *
 * Eventos estándar: https://developers.facebook.com/docs/meta-pixel/reference
 */

export const META_PIXEL_ID = (process.env.NEXT_PUBLIC_META_PIXEL_ID ?? "").trim();

/** Los IDs del píxel de Meta son solo dígitos (15–16 normalmente). */
export const metaPixelEnabled = /^\d{10,20}$/.test(META_PIXEL_ID);

export type MetaStandardEvent =
  | "PageView"
  | "ViewContent"
  | "AddToCart"
  | "InitiateCheckout"
  | "AddPaymentInfo"
  | "Purchase"
  | "Contact"
  | "Lead";

export interface MetaContent {
  id: string;
  quantity: number;
  item_price?: number;
}

export interface MetaEventParams {
  content_ids?: string[];
  content_type?: "product";
  content_name?: string;
  contents?: MetaContent[];
  num_items?: number;
  value?: number;
  currency?: string;
  [extra: string]: unknown;
}

type Fbq = (...args: unknown[]) => void;

function fbq(): Fbq | null {
  if (!metaPixelEnabled || typeof window === "undefined") return null;
  return ((window as unknown as { fbq?: Fbq }).fbq ?? null);
}

export function metaPageView(): void {
  try {
    fbq()?.("track", "PageView");
  } catch {
    // Nunca romper la navegación por la analítica.
  }
}

/**
 * Evento estándar. `eventId` deduplica contra la API de Conversiones (servidor) cuando se
 * agregue: Meta cuenta uno solo si llega el mismo id por los dos lados.
 */
export function metaTrack(event: MetaStandardEvent, params: MetaEventParams = {}, eventId?: string): void {
  try {
    fbq()?.("track", event, params, eventId ? { eventID: eventId } : undefined);
  } catch {
    // Nunca romper la compra por la analítica.
  }
}

/** Evento propio (no estándar), por ejemplo un pedido creado que aún no está pagado. */
export function metaTrackCustom(event: string, params: MetaEventParams = {}, eventId?: string): void {
  try {
    fbq()?.("trackCustom", event, params, eventId ? { eventID: eventId } : undefined);
  } catch {
    // Ignorar.
  }
}

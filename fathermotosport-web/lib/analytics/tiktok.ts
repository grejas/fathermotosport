/**
 * Píxel de TikTok: capa baja. Habla con `window.ttq`, que carga <TikTokPixel /> en el layout.
 *
 * Si NEXT_PUBLIC_TIKTOK_PIXEL_ID no está definido (desarrollo local, previews) el píxel no
 * se carga y todas estas funciones no hacen nada. Registrar un evento nunca debe romper
 * la navegación ni la compra: cada llamada va envuelta en try/catch.
 *
 * Eventos estándar de TikTok usados por el sitio:
 * https://business-api.tiktok.com/portal/docs?id=1739585702922241
 */

/** ID del píxel (público: viaja en el HTML de todas formas). Vacío = píxel apagado. */
export const TIKTOK_PIXEL_ID = (process.env.NEXT_PUBLIC_TIKTOK_PIXEL_ID ?? "").trim();

/** Los IDs de píxel de TikTok son alfanuméricos en mayúsculas (~20 caracteres). */
export const tiktokPixelEnabled = /^[A-Z0-9]{10,40}$/.test(TIKTOK_PIXEL_ID);

export type TikTokEvent =
  | "ViewContent"
  | "AddToCart"
  | "InitiateCheckout"
  | "AddPaymentInfo"
  | "PlaceAnOrder"
  | "Purchase"
  | "Contact"
  | "Lead";

export interface TikTokContent {
  content_id: string;
  content_type: "product";
  content_name?: string;
  quantity?: number;
  price?: number;
}

export interface TikTokEventParams {
  contents?: TikTokContent[];
  value?: number;
  currency?: string;
  /** Texto libre, por ejemplo de dónde salió un clic de WhatsApp. */
  description?: string;
}

interface Ttq {
  page: () => void;
  track: (event: string, params?: Record<string, unknown>, options?: { event_id?: string }) => void;
  identify: (data: Record<string, string>) => void;
}

function ttq(): Ttq | null {
  if (!tiktokPixelEnabled || typeof window === "undefined") return null;
  return ((window as unknown as { ttq?: Ttq }).ttq ?? null);
}

/** Vista de página. La primera la registra el script base; esta es para la navegación interna. */
export function tiktokPage(): void {
  try {
    ttq()?.page();
  } catch {
    // Nunca romper la navegación por la analítica.
  }
}

/**
 * Envía un evento. `eventId` sirve para deduplicar cuando más adelante se mande el mismo
 * evento también desde el servidor (Events API): TikTok cuenta uno solo si coinciden.
 */
export function tiktokTrack(event: TikTokEvent, params: TikTokEventParams = {}, eventId?: string): void {
  try {
    ttq()?.track(event, params as Record<string, unknown>, eventId ? { event_id: eventId } : undefined);
  } catch {
    // Nunca romper la compra por la analítica.
  }
}

async function sha256(texto: string): Promise<string | null> {
  try {
    if (!globalThis.crypto?.subtle) return null;
    const bytes = new TextEncoder().encode(texto);
    const hash = await crypto.subtle.digest("SHA-256", bytes);
    return Array.from(new Uint8Array(hash))
      .map((b) => b.toString(16).padStart(2, "0"))
      .join("");
  } catch {
    return null;
  }
}

/**
 * Coincidencia avanzada: le dice a TikTok quién es el comprador para atribuir mejor la venta
 * a un video o anuncio. Se envía SOLO el hash SHA-256 del email y del teléfono, nunca el dato
 * en claro, como pide TikTok.
 */
export async function tiktokIdentify(email?: string | null, phone?: string | null): Promise<void> {
  if (!ttq()) return;
  const datos: Record<string, string> = {};

  const emailLimpio = email?.trim().toLowerCase();
  if (emailLimpio && emailLimpio.includes("@")) {
    const h = await sha256(emailLimpio);
    if (h) datos.email = h;
  }

  // Formato E.164: "+" y solo dígitos. Sin código de país no se envía (no coincidiría).
  const digitos = phone?.replace(/[^\d+]/g, "") ?? "";
  if (digitos.startsWith("+") && digitos.length >= 8) {
    const h = await sha256(digitos);
    if (h) datos.phone_number = h;
  }

  if (!Object.keys(datos).length) return;
  try {
    ttq()?.identify(datos);
  } catch {
    // Ignorar.
  }
}

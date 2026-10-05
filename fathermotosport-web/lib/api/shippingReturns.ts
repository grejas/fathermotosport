/** Tag de caché compartido con Laravel (ShippingReturnsPage::REVALIDATE_TAG). */
export const SHIPPING_RETURNS_TAG = "shipping-returns";

export type ShippingReturnsTextField =
  | "badge_shipping"
  | "badge_returns"
  | "summary_shipping"
  | "summary_damaged"
  | "summary_withdrawal"
  | "page_title"
  | "page_intro"
  | "damaged_title"
  | "withdrawal_title"
  | "process_title"
  | "cancellations_title"
  | "help_text";

export type ShippingReturnsListField =
  | "damaged_items"
  | "withdrawal_items"
  | "process_items"
  | "cancellations_items";

/** Respuesta de GET /store/shipping-returns: null = campo vacío en el panel. */
export interface ShippingReturnsApi {
  locale: string;
  damage_report_hours: number | null;
  withdrawal_days: number | null;
  texts: Record<ShippingReturnsTextField, string | null> &
    Record<ShippingReturnsListField, string[] | null>;
  contact: { email: string | null; whatsapp: string | null };
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/**
 * Textos de envío y devoluciones editados desde el panel. Solo para el servidor.
 *
 * Usa fetch nativo (no axios) para entrar en la Data Cache de Next: se renueva cada
 * 10 minutos y al instante cuando Laravel llama a /api/revalidate con este tag.
 * Ante cualquier error devuelve null y la web muestra los textos de messages/*.json.
 */
export async function getShippingReturns(locale: string): Promise<ShippingReturnsApi | null> {
  try {
    const res = await fetch(`${API_URL}/store/shipping-returns?locale=${encodeURIComponent(locale)}`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 600, tags: [SHIPPING_RETURNS_TAG] },
    });
    if (!res.ok) return null;

    const json = (await res.json()) as { data?: ShippingReturnsApi };
    return json.data ?? null;
  } catch {
    return null;
  }
}

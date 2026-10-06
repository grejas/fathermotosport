/** Tag de caché compartido con Laravel (ShippingReturnsPage::REVALIDATE_TAG). */
export const SHIPPING_RETURNS_TAG = "shipping-returns";

/** Respuesta de GET /store/shipping-returns: null = campo vacío en el panel. */
export interface ShippingReturnsApi {
  locale: string;
  damage_report_hours: number | null;
  texts: {
    badge_returns: string | null;
    /** Texto plano; los párrafos van separados por una línea en blanco. */
    summary: string | null;
    page_title: string | null;
    /** HTML del RichEditor (Laravel ya lo sanitiza; la web lo vuelve a sanitizar). */
    page_body: string | null;
  };
  contact: { email: string | null; whatsapp: string | null };
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/**
 * Política de envío y devoluciones editada desde el panel. Solo para el servidor.
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

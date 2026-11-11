/**
 * Google Analytics 4: capa baja. Habla con `window.gtag`, que carga <AnalyticsScripts />.
 *
 * Sin NEXT_PUBLIC_GA4_ID (formato G-XXXXXXX) no se carga nada y estas funciones no hacen
 * nada. Las vistas de página las registra GA4 solo: la primera con la config y las
 * siguientes con la "medición mejorada" (cambios de historial), que viene activada en GA4.
 * Por eso aquí no se envían a mano: se contarían dos veces.
 *
 * Eventos de comercio: https://developers.google.com/analytics/devguides/collection/ga4/ecommerce
 */

export const GA4_ID = (process.env.NEXT_PUBLIC_GA4_ID ?? "").trim();

export const ga4Enabled = /^G-[A-Z0-9]{4,20}$/.test(GA4_ID);

export interface Ga4Item {
  item_id: string;
  item_name?: string;
  price?: number;
  quantity?: number;
}

export interface Ga4Params {
  currency?: string;
  value?: number;
  items?: Ga4Item[];
  transaction_id?: string;
  shipping?: number;
  payment_type?: string;
  [extra: string]: unknown;
}

type Gtag = (...args: unknown[]) => void;

function gtag(): Gtag | null {
  if (!ga4Enabled || typeof window === "undefined") return null;
  return ((window as unknown as { gtag?: Gtag }).gtag ?? null);
}

export function ga4Event(name: string, params: Ga4Params = {}): void {
  try {
    gtag()?.("event", name, params);
  } catch {
    // Nunca romper la compra por la analítica.
  }
}

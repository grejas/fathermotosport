/**
 * Eventos del modal de venta cruzada. Van a GA4 (upsell_<evento>) y a Meta como evento
 * personalizado (Upsell_<evento>), así se puede medir el embudo del upsell: cuántos lo
 * ven, cuántos intentan cerrarlo, cuántos aceptan la rebaja y cuántos compran.
 *
 * La COMPRA del upsell no sale de acá: el pedido de venta cruzada pagado se registra
 * como cualquier otro con OrderConversionTracker (Purchase en TikTok, Meta y GA4).
 */
import { ga4Event } from "@/lib/analytics/ga4";
import { metaTrackCustom } from "@/lib/analytics/meta";

export type UpsellEvent =
  | "modal_visto"
  | "cierre_paso_1"
  | "cierre_paso_2"
  | "cerrado"
  | "pedido_creado"
  | "agregado"
  | "pago_fallido";

export function trackUpsell(evento: UpsellEvent, datos: Record<string, unknown> = {}): void {
  try {
    // Solo valores simples: GA4 y Meta descartan objetos anidados.
    const plano = Object.fromEntries(
      Object.entries(datos).filter(([, v]) => ["string", "number", "boolean"].includes(typeof v))
    );
    ga4Event(`upsell_${evento}`, plano);
    metaTrackCustom(`Upsell_${evento}`, plano);
    if (process.env.NODE_ENV !== "production") console.debug("[upsell]", evento, plano);
  } catch {
    // Registrar un evento nunca debe romper la compra.
  }
}

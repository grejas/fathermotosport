/**
 * Eventos del modal de venta cruzada. El sitio todavía no tiene una herramienta de
 * analítica, así que por ahora quedan en la consola (console.debug, oculto por
 * defecto). Cuando se conecte una, se cambia solo esta función.
 */
export type UpsellEvent = "modal_visto" | "cierre_paso_1" | "cierre_paso_2" | "cerrado" | "agregado";

export function trackUpsell(evento: UpsellEvent, datos: Record<string, unknown> = {}): void {
  try {
    console.debug("[upsell]", evento, datos);
  } catch {
    // Registrar un evento nunca debe romper la compra.
  }
}

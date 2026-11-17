/**
 * Ventana de la campaña Black Friday / Cyber Monday 2026 (hora de Bolivia, UTC-4).
 *
 * - teaser:  aviso en la home y en /black-friday ("empieza en ...").
 * - live:    se muestran los productos con descuento REAL cargado en el panel.
 * - ended:   todo desaparece solo; /black-friday manda al catálogo.
 *
 * Los porcentajes NO se definen aquí: salen de los descuentos por categoría o por
 * producto programados en el panel. Esta página solo muestra lo que ya está aplicado.
 */
export const BLACK_FRIDAY = {
  teaserFrom: "2026-11-17T00:00:00-04:00",
  startsAt: "2026-11-24T00:00:00-04:00",
  endsAt: "2026-11-30T23:59:00-04:00",
} as const;

export type CampaignPhase = "before" | "teaser" | "live" | "ended";

export function blackFridayPhase(now: number = Date.now()): CampaignPhase {
  if (now < Date.parse(BLACK_FRIDAY.teaserFrom)) return "before";
  if (now < Date.parse(BLACK_FRIDAY.startsAt)) return "teaser";
  if (now <= Date.parse(BLACK_FRIDAY.endsAt)) return "live";
  return "ended";
}

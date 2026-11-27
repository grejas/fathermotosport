/**
 * Fechas estimadas de entrega a partir de un plazo en días hábiles.
 *
 * Los plazos de cada opción de envío (estimated_days_min / estimated_days_max,
 * editables en el panel → Opciones de envío) son días hábiles TOTALES: procesamiento
 * (compra al proveedor y despacho desde China) + tránsito hasta el país del cliente.
 * Sábados y domingos no cuentan.
 */

/** Suma `days` días hábiles (lunes a viernes) a `from`, sin modificar `from`. */
export function addBusinessDays(from: Date, days: number): Date {
  const result = new Date(from.getFullYear(), from.getMonth(), from.getDate());
  let remaining = Math.max(0, Math.floor(days));
  while (remaining > 0) {
    result.setDate(result.getDate() + 1);
    const weekday = result.getDay();
    if (weekday !== 0 && weekday !== 6) remaining--;
  }
  return result;
}

/** Rango de entrega { from, to } para un plazo min–max en días hábiles. */
export function deliveryRange(
  minDays: number,
  maxDays: number,
  today: Date = new Date()
): { from: Date; to: Date } {
  const lo = Math.min(minDays, maxDays);
  const hi = Math.max(minDays, maxDays);
  return { from: addBusinessDays(today, lo), to: addBusinessDays(today, hi) };
}

/** "18 dic" / "18 de dez" / "Dec 18" según el idioma del sitio. */
export function formatDeliveryDate(date: Date, locale: string): string {
  const tag = locale === "pt" ? "pt-BR" : locale === "en" ? "en-US" : "es";
  return new Intl.DateTimeFormat(tag, { day: "numeric", month: "short" })
    .format(date)
    .replace(/\.$/, "");
}

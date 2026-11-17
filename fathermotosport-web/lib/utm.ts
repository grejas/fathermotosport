/**
 * Origen de la visita (UTM). Se guarda el PRIMER origen con el que llegó la persona
 * (por ejemplo source=tiktok, campaign=black_friday) para poder decir luego de dónde
 * vino un contacto o una venta, aunque haya navegado por varias páginas.
 */
const KEY = "fms-utm";
const CAMPOS = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "ttclid", "fbclid", "gclid"];

export function captureUtm(): void {
  if (typeof window === "undefined") return;
  try {
    const params = new URLSearchParams(window.location.search);
    const encontrados: Record<string, string> = {};
    for (const c of CAMPOS) {
      const v = params.get(c);
      if (v) encontrados[c] = v.slice(0, 200);
    }
    if (Object.keys(encontrados).length && !localStorage.getItem(KEY)) {
      localStorage.setItem(KEY, JSON.stringify({ ...encontrados, landing: window.location.pathname }));
    }
  } catch {
    // Sin localStorage (modo privado estricto): no pasa nada.
  }
}

export function readUtm(): Record<string, string> {
  if (typeof window === "undefined") return {};
  try {
    return JSON.parse(localStorage.getItem(KEY) || "{}") as Record<string, string>;
  } catch {
    return {};
  }
}

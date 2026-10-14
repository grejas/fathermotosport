/**
 * Traducción al vuelo (es → pt/en) de textos cargados en el panel, y la regla de que los
 * nombres de marca NUNCA se traducen. Este archivo es el único punto de verdad de esa regla:
 * la lista de marcas sale de la API (/brands), así que una marca nueva queda protegida sola.
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/** La marca de la tienda: protegida aunque la API no la liste o no responda. */
const MARCA_PROPIA = "FatherMotoSport";

let nombresProtegidos: Promise<string[]> | null = null;

/**
 * Nombres de marca que no se traducen: los de la API más FatherMotoSport, del más largo
 * al más corto (para que "Breathe Divinity" gane a una marca contenida en él).
 * Se piden una vez por visita; si la API falla, se usa solo FatherMotoSport y se
 * reintenta en la próxima llamada.
 */
export function getProtectedNames(): Promise<string[]> {
  nombresProtegidos ??= fetch(`${API_URL}/brands`, { headers: { Accept: "application/json" } })
    .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
    .then((json: { data?: { name?: string | null }[] }) => (json.data ?? []).map((b) => b.name ?? ""))
    .catch(() => {
      nombresProtegidos = null;
      return [] as string[];
    })
    .then((nombres) => {
      const unicos = new Map<string, string>();
      for (const n of [...nombres, MARCA_PROPIA].map((n) => n.trim()).filter(Boolean)) {
        if (!unicos.has(n.toLowerCase())) unicos.set(n.toLowerCase(), n);
      }
      return [...unicos.values()].sort((a, b) => b.length - a.length);
    });
  return nombresProtegidos;
}

/**
 * Una marca como palabra completa, sin distinguir mayúsculas, y con cualquier espacio
 * entre sus palabras. El grupo 1 es el carácter previo (no se usa lookbehind, que Safari
 * viejo no soporta).
 */
function patronDeMarcas(nombres: string[]): RegExp | null {
  if (!nombres.length) return null;
  const alternativas = nombres
    .map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, "\\$&").replace(/\s+/g, "\\s+"))
    .join("|");
  return new RegExp(`(^|[^\\p{L}\\p{N}])(${alternativas})(?![\\p{L}\\p{N}])`, "giu");
}

/** El texto en trozos, marcando cuáles son nombres de marca (para envolverlos en translate="no"). */
export function splitByProtectedNames(texto: string, nombres: string[]): { texto: string; marca: boolean }[] {
  const patron = patronDeMarcas(nombres);
  if (!patron) return [{ texto, marca: false }];

  const trozos: { texto: string; marca: boolean }[] = [];
  let desde = 0;
  for (const m of texto.matchAll(patron)) {
    const inicio = m.index! + m[1].length;
    if (inicio > desde) trozos.push({ texto: texto.slice(desde, inicio), marca: false });
    trozos.push({ texto: m[2], marca: true });
    desde = inicio + m[2].length;
  }
  if (desde < texto.length) trozos.push({ texto: texto.slice(desde), marca: false });
  return trozos;
}

/**
 * Marcador que el traductor trata como un nombre propio (deja intacto y ordena como la
 * marca: "Dainese gloves", no "Gloves Dainese"). Dos letras fijas tras el prefijo para
 * que ninguno sea prefijo de otro: Zqxvaa, Zqxvab… (676 posibles).
 */
const marcador = (i: number) =>
  `Zqxv${String.fromCharCode(97 + Math.floor(i / 26))}${String.fromCharCode(97 + (i % 26))}`;
const PATRON_MARCADOR = /zqxv[a-z]{2}/gi;

async function traducirConGoogle(texto: string, targetLang: string): Promise<string> {
  const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=es&tl=${targetLang}&dt=t&q=${encodeURIComponent(texto)}`;
  const res = await fetch(url);
  const data = await res.json();
  return (data[0] as [string, string][]).map((item) => item[0]).join("");
}

/**
 * Traduce un texto del panel (en español) al idioma activo, sin traducir las marcas:
 * cada marca se cambia por un marcador antes de traducir y se repone después. Si algún
 * marcador vuelve alterado, perdido o repetido, se devuelve el original sin traducir.
 */
export async function translateText(text: string, targetLang: string): Promise<string> {
  if (targetLang === "es" || !text.trim()) return text;
  // Un texto que ya trae algo con forma de marcador no se puede proteger con seguridad.
  if (text.match(PATRON_MARCADOR)) return text;
  try {
    const marcas: string[] = [];
    const patron = patronDeMarcas(await getProtectedNames());
    const protegido = patron
      ? text.replace(patron, (_, previo: string, marca: string) => `${previo}${marcador(marcas.push(marca) - 1)}`)
      : text;
    if (marcas.length > 676) return text;

    const traducido = await traducirConGoogle(protegido, targetLang);

    // Cada marcador tiene que volver exactamente una vez (se acepta otro uso de mayúsculas).
    const encontrados = (traducido.match(PATRON_MARCADOR) ?? []).map((m) => m.toLowerCase());
    const intactos =
      encontrados.length === marcas.length &&
      marcas.every((_, i) => encontrados.filter((m) => m === marcador(i).toLowerCase()).length === 1);
    if (!intactos) return text;

    return traducido.replace(PATRON_MARCADOR, (m) => marcas[encontrados.indexOf(m.toLowerCase())]);
  } catch {
    return text;
  }
}

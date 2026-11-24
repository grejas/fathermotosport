/**
 * Loader de next/image: las fotos de la API se piden en WebP y al ancho que hace falta
 * (/storage/v/{ancho}/{ruta}.webp), que la API genera una vez y después sirve como
 * archivo estático. Reemplaza al optimizador de Vercel, que cobra por imagen (se quedó
 * sin cuota y dejó la tienda sin fotos).
 *
 * Cualquier otra imagen (locales, GIF, SVG, otros dominios) sale tal cual.
 */
const ORIGEN = "https://api.fathermotosport.com/storage/";
const ANCHOS = [320, 640, 960, 1280, 1920];
const CONVERTIBLE = /\.(jpe?g|png|webp)$/i;

/** Cada tramo de la ruta, codificado una sola vez (en srcset un espacio rompe la URL). */
function codificar(ruta: string): string {
  return ruta
    .split("/")
    .map((tramo) => {
      try {
        return encodeURIComponent(decodeURIComponent(tramo));
      } catch {
        return encodeURIComponent(tramo);
      }
    })
    .join("/");
}

export default function imageLoader({ src, width }: { src: string; width: number; quality?: number }): string {
  if (!src.startsWith(ORIGEN)) return src;

  const ruta = src.slice(ORIGEN.length).split(/[?#]/)[0];
  if (ruta.startsWith("v/") || !CONVERTIBLE.test(ruta)) return src;

  const ancho = ANCHOS.find((a) => a >= width) ?? ANCHOS[ANCHOS.length - 1];
  return `${ORIGEN}v/${ancho}/${codificar(ruta)}.webp`;
}

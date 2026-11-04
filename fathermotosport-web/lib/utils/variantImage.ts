import type { ProductImage } from "@/lib/types";

/** "Fénix Amarillo" → "fenix-amarillo" (sin tildes, minúsculas, separado por guiones). */
export function slugify(text: string): string {
  return text
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

/** Nombre de archivo sin ruta, prefijo hash de subida ni extensión: "6aeb..._alas-led-verde.jpg" → "alas-led-verde". */
function fileSlug(url: string): string {
  const file = decodeURIComponent(url.split("?")[0].split("/").pop() ?? "");
  const noExt = file.replace(/\.[a-z0-9]+$/i, "");
  return slugify(noExt.replace(/^[0-9a-f]{8,}_/i, ""));
}

/**
 * Imagen que corresponde a una opción (talla/color/modelo) del producto, por nombre de archivo:
 * la opción "Verde" se muestra con "…-verde.jpg"; "Fénix Amarillo" con "…-fenix-amarillo.jpg".
 * Solo cuenta si el nombre de la opción aparece completo (como palabras) en el archivo; si no hay
 * una foto para esa opción devuelve null y la galería queda como está. Se prefiere la foto cuyo
 * nombre termina en la opción (la principal) sobre variantes como "-verde-estacionamiento".
 */
export function variantImage(images: ProductImage[] | undefined, option: string | null | undefined): ProductImage | null {
  if (!images?.length || !option) return null;
  const target = slugify(option);
  if (!target) return null;
  const pattern = new RegExp(`(^|-)${target}(-|$)`);
  const matches = images.filter((img) => (img.type ?? "image") === "image" && pattern.test(fileSlug(img.url)));
  if (matches.length === 0) return null;
  return matches.find((img) => fileSlug(img.url).endsWith(target)) ?? matches[0];
}

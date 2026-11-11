import type { ProductImage, ProductVariant } from "@/lib/types";

/**
 * Colores elegibles de un producto: los nombres distintos de color de sus variantes, en el
 * orden en que aparecen. Un producto de un solo color (casi todo el catálogo) devuelve [],
 * porque ahí el color es informativo y no hay nada que elegir.
 */
export function variantColors(variants: ProductVariant[] | undefined): string[] {
  const colors = [...new Set((variants ?? []).map((v) => v.color?.trim()).filter((c): c is string => !!c))];
  return colors.length >= 2 ? colors : [];
}

/**
 * Fotos y videos que se muestran con el color elegido: los de ese color y los que sirven
 * para todos (sin color). Sin color elegido, la galería queda completa.
 */
export function mediaForColor(images: ProductImage[] | undefined, color: string | null): ProductImage[] {
  const all = images ?? [];
  if (!color) return all;
  return all.filter((img) => !img.color || img.color === color);
}

/** Primera foto (no video ni GIF) del color: es la que viaja al carrito y al checkout. */
export function colorCover(images: ProductImage[] | undefined, color: string | null | undefined): ProductImage | null {
  if (!color) return null;
  return (images ?? []).find((img) => img.color === color && (img.type ?? "image") === "image") ?? null;
}

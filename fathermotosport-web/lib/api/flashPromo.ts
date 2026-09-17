import apiClient from "./client";

export interface FlashPromo {
  id: number;
  promo_text: string | null;
  /** Timestamp ISO exacto de cuándo termina esta promo. */
  ends_at: string | null;
  /** Categorías a las que aplica (ids y slugs). Vacío => toda la tienda. */
  category_ids: number[];
  category_slugs: string[];
  /** true cuando no tiene categorías => aplica a toda la tienda. */
  applies_to_all: boolean;
}

/**
 * Todas las promociones flash vigentes AHORA. Ante cualquier error de red
 * devuelve [] para que no se muestre nada.
 */
export async function getFlashPromos(): Promise<FlashPromo[]> {
  try {
    const { data } = await apiClient.get<{ data: FlashPromo[] }>("/flash-promo");
    return Array.isArray(data.data) ? data.data : [];
  } catch {
    return [];
  }
}

/**
 * ¿Esta promo aplica a un producto de la categoría dada?
 * - Promo de toda la tienda (applies_to_all) => aplica a cualquier producto.
 * - Promo segmentada => solo si coincide el id o el slug de la categoría.
 */
export function flashPromoAppliesTo(
  promo: FlashPromo,
  categoryId?: number | string | null,
  categorySlug?: string | null
): boolean {
  if (!promo.promo_text || !promo.ends_at) return false;
  if (promo.applies_to_all) return true;
  if (categoryId != null && promo.category_ids.includes(Number(categoryId))) return true;
  if (categorySlug != null && promo.category_slugs.includes(categorySlug)) return true;
  return false;
}

/**
 * Elige la promo a mostrar en una página de producto: de las que aplican a su
 * categoría, prioriza la específica de categoría sobre la de toda la tienda.
 */
export function pickPromoForCategory(
  promos: FlashPromo[],
  categoryId?: number | string | null,
  categorySlug?: string | null
): FlashPromo | null {
  const applicable = promos.filter((p) => flashPromoAppliesTo(p, categoryId, categorySlug));
  return applicable.find((p) => !p.applies_to_all) ?? applicable[0] ?? null;
}

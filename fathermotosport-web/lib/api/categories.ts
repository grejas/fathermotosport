import type { Category } from "@/lib/types";

/** Tag de caché compartido con Laravel (Category::REVALIDATE_TAG). */
export const CATEGORIES_TAG = "categories";

/** Lo que necesitan los menús: el id real (para /catalog?category=<id>) y cómo nombrarla. */
export interface MenuCategory {
  id: number;
  slug: string;
  name: string;
  icon: string | null;
  /** 0 para las raíz, 1 para sus subcategorías. */
  depth: number;
}

/** Enlaces directos del navbar, por slug y en este orden. Si un slug no está activo, no se muestra. */
export const FEATURED_SLUGS = ["cascos", "guantes", "botas", "chamarras", "hoddie"] as const;

/**
 * Slug → clave de traducción en messages/{locale}.json (namespace "nav").
 * Una categoría sin entrada acá (o sin la clave en el idioma) usa el nombre del panel.
 */
const NAV_KEYS: Record<string, string> = {
  cascos: "helmets",
  guantes: "gloves",
  botas: "boots",
  chamarras: "jackets",
  repuestos: "parts",
  accesorios: "accessories",
  viseras: "visors",
  hoddie: "hoodies",
};

export function categoryHref(c: Pick<MenuCategory, "id">): string {
  return `/catalog?category=${c.id}`;
}

type Translator = { (key: string): string; has(key: string): boolean };

/** Nombre traducido si hay clave en nav.*; si no, el del panel. */
export function categoryLabel(t: Translator, c: Pick<MenuCategory, "slug" | "name">): string {
  const key = NAV_KEYS[c.slug];
  return key && t.has(key) ? t(key) : c.name;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/**
 * Categorías activas en orden de sort_order (cada subcategoría tras su padre), leídas
 * en el servidor. Usa fetch nativo para entrar en la Data Cache de Next: se renueva cada
 * 10 minutos y al instante cuando Laravel llama a /api/revalidate con este tag.
 * Ante cualquier error devuelve [] y los menús quedan sin categorías, sin romperse.
 */
export async function getMenuCategories(): Promise<MenuCategory[]> {
  try {
    const res = await fetch(`${API_URL}/categories`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 600, tags: [CATEGORIES_TAG] },
    });
    if (!res.ok) {
      console.error(`[categorías] La API respondió ${res.status}; los menús se muestran sin categorías.`);
      return [];
    }

    const json = (await res.json()) as { data?: Category[] };
    return flatten(json.data ?? [], 0);
  } catch (e) {
    console.error("[categorías] No se pudo leer la API; los menús se muestran sin categorías.", e);
    return [];
  }
}

function flatten(categories: Category[], depth: number): MenuCategory[] {
  return [...categories]
    .filter((c) => c.is_active !== false)
    .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
    .flatMap((c) => [
      { id: c.id, slug: c.slug, name: c.name, icon: c.icon, depth },
      ...flatten(c.children ?? [], depth + 1),
    ]);
}

/** Los enlaces directos del navbar, en el orden de FEATURED_SLUGS. */
export function featuredCategories(categories: MenuCategory[]): MenuCategory[] {
  return FEATURED_SLUGS.flatMap((slug) => categories.filter((c) => c.slug === slug).slice(0, 1));
}

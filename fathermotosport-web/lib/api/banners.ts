import apiClient from "./client";

/** Tag de caché compartido con Laravel (Banner::REVALIDATE_TAG). */
export const BANNERS_TAG = "banners";

/**
 * Posiciones que se dibujan en la web. "sidebar" sigue existiendo en la base para
 * banners viejos, pero ninguna página la muestra y el panel ya no la ofrece.
 */
export type BannerPosition = "hero" | "home_after_categories" | "footer" | "sidebar";

export interface Banner {
  id: number;
  /** Opcional. El hero no lo usa (tiene textos fijos); sí "Debajo de categorías" y el footer. */
  title: string | null;
  /** Texto opcional debajo del título. Solo lo usa "Debajo de categorías". */
  subtitle: string | null;
  image_url: string | null;
  link_url: string | null;
  button_text: string | null;
  position: BannerPosition;
  is_active: boolean;
  sort_order: number;
  media_type: "image" | "video";
  /** URLs absolutas, en https y codificadas; null si el banner es de imagen. */
  video_url: string | null;
  video_mobile_url: string | null;
  poster_url: string | null;
  /** El video arranca siempre en silencio; con esto el visitante puede activar el sonido. */
  video_with_sound: boolean;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/** ¿Tiene algo que mostrar? Un video necesita su archivo (el poster es opcional); una imagen, su imagen. */
export function bannerUsable(b: Banner): boolean {
  return b.media_type === "video" ? Boolean(b.video_url) : Boolean(b.image_url);
}

/**
 * Banners activos de una posición, leídos en el servidor. Usa fetch nativo (no axios)
 * para entrar en la Data Cache de Next: se renueva cada 10 minutos y al instante
 * cuando Laravel llama a /api/revalidate con este tag (al guardar en el panel).
 * Ante cualquier error devuelve [] y la sección no se muestra.
 */
export async function getBannersServidor(position: BannerPosition): Promise<Banner[]> {
  try {
    const res = await fetch(`${API_URL}/banners/${position}`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 600, tags: [BANNERS_TAG] },
    });
    if (!res.ok) return [];

    const json = (await res.json()) as { data?: Banner[] };
    return (json.data ?? []).filter(bannerUsable);
  } catch {
    return [];
  }
}

/**
 * Banners activos desde el navegador. Si se pasa `position`, solo los de esa
 * ubicación. Lanza ante un error de red (quien llama decide qué mostrar).
 */
export async function getBanners(position?: BannerPosition): Promise<Banner[]> {
  const url = position ? `/banners/${position}` : "/banners";
  const { data } = await apiClient.get<{ data: Banner[] }>(url);
  return data.data;
}

import apiClient from "./client";

export type BannerPosition = "hero" | "sidebar" | "footer";

export interface Banner {
  id: number;
  title: string;
  image_url: string | null;
  link_url: string | null;
  button_text: string | null;
  position: BannerPosition;
  is_active: boolean;
  sort_order: number;
}

/**
 * Banners activos. Si se pasa `position`, solo los de esa ubicación
 * (hero / sidebar / footer). Devuelve [] ante cualquier error de red.
 */
export async function getBanners(position?: BannerPosition): Promise<Banner[]> {
  const url = position ? `/banners/${position}` : "/banners";
  const { data } = await apiClient.get<{ data: Banner[] }>(url);
  return data.data;
}

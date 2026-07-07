import apiClient from "./client";

export interface StoreConfig {
  store_name: string;
  logo_url: string | null;
  favicon_url: string | null;
  phone: string | null;
  email: string | null;
  currency: string;
  whatsapp: string | null;
  facebook: string | null;
  instagram: string | null;
  youtube: string | null;
  maintenance_mode: boolean;
}

/**
 * Configuración pública de la tienda (logo, favicon, contacto, redes).
 * Devuelve null ante cualquier error de red o si no hay configuración.
 */
export async function getStoreConfig(): Promise<StoreConfig | null> {
  try {
    const { data } = await apiClient.get<{ data: StoreConfig | null }>("/store-config");
    return data.data;
  } catch {
    return null;
  }
}

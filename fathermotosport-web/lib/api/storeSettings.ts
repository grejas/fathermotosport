import { CONTACT_EMAIL, CONTACT_WHATSAPP } from "@/lib/data/contact";

/** Tag de caché compartido con Laravel (StoreConfig::REVALIDATE_TAG). */
export const STORE_SETTINGS_TAG = "store-settings";

export type SocialNetwork = "facebook" | "instagram" | "youtube" | "tiktok";

/** Respuesta de GET /store/settings: null = campo vacío en el panel. */
export interface StoreSettingsApi {
  store_name: string | null;
  email: string | null;
  phone: string | null;
  /** Solo dígitos. */
  whatsapp: string | null;
  social: Partial<Record<SocialNetwork, string | null>>;
}

/**
 * Lo que usa la web: el correo siempre resuelto; WhatsApp null si está vacío en el panel
 * (no se muestra ningún enlace ni botón); una red sin enlace válido es null.
 */
export interface StoreSettings {
  email: string;
  /** Solo dígitos, para https://wa.me/<dígitos>. null = vacío en el panel. */
  whatsappDigits: string | null;
  social: Record<SocialNetwork, string | null>;
}

/** Mismos dominios que StoreConfig::SOCIAL_DOMAINS (más su variante www.). */
const SOCIAL_DOMAINS: Record<SocialNetwork, string[]> = {
  facebook: ["facebook.com", "fb.com", "m.facebook.com"],
  instagram: ["instagram.com"],
  youtube: ["youtube.com", "youtu.be"],
  tiktok: ["tiktok.com"],
};

export const SOCIAL_NETWORKS = Object.keys(SOCIAL_DOMAINS) as SocialNetwork[];

/**
 * Solo si la API falla: correo y WhatsApp de respaldo y ninguna red (así nunca vuelve
 * un enlace viejo escrito en el código).
 */
export const FALLBACK_STORE_SETTINGS: StoreSettings = {
  email: CONTACT_EMAIL,
  whatsappDigits: CONTACT_WHATSAPP,
  social: { facebook: null, instagram: null, youtube: null, tiktok: null },
};

/** El enlace solo se pinta si es https y del dominio de esa red (la API ya lo valida). */
export function safeSocialUrl(network: SocialNetwork, value: unknown): string | null {
  if (typeof value !== "string" || value.trim() === "") return null;
  try {
    const url = new URL(value.trim());
    const host = url.hostname.toLowerCase().replace(/^www\./, "");
    if (url.protocol !== "https:" || url.username || url.password || url.port) return null;
    return SOCIAL_DOMAINS[network].includes(host) ? url.href : null;
  } catch {
    return null;
  }
}

/** Enlace a WhatsApp, o null si no hay número (entonces no se pinta enlace ni botón). */
export function whatsappUrl(digits: string | null, message?: string): string | null {
  const limpio = (digits ?? "").replace(/\D/g, "");
  if (!limpio) return null;
  return `https://wa.me/${limpio}${message ? `?text=${encodeURIComponent(message)}` : ""}`;
}

/**
 * Respuesta de la API → ajustes de la web. null (la API falló) → respaldo completo.
 * Con API: un correo vacío usa el de respaldo, pero un WhatsApp vacío queda en null.
 */
export function resolveStoreSettings(api: StoreSettingsApi | null): StoreSettings {
  if (!api) return FALLBACK_STORE_SETTINGS;

  const email = typeof api.email === "string" ? api.email.trim() : "";
  const whatsapp = typeof api.whatsapp === "string" ? api.whatsapp.replace(/\D/g, "") : "";

  return {
    email: email || CONTACT_EMAIL,
    whatsappDigits: whatsapp || null,
    social: Object.fromEntries(
      SOCIAL_NETWORKS.map((network) => [network, safeSocialUrl(network, api.social?.[network])])
    ) as Record<SocialNetwork, string | null>,
  };
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1";

/**
 * Contacto y redes de "Configuración de la tienda", leídos en el servidor. Usa fetch
 * nativo para entrar en la Data Cache de Next: se renueva cada 10 minutos y al instante
 * cuando Laravel llama a /api/revalidate con este tag (Next deduplica las lecturas de una
 * misma petición). Ante cualquier error devuelve el respaldo y la página se muestra igual.
 * Este módulo también lo importa el provider de cliente, solo por los tipos y el respaldo.
 */
export async function getStoreSettings(): Promise<StoreSettings> {
  try {
    const res = await fetch(`${API_URL}/store/settings`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 600, tags: [STORE_SETTINGS_TAG] },
    });
    if (!res.ok) {
      console.error(`[ajustes] La API respondió ${res.status}; se usa el contacto de respaldo y sin redes.`);
      return FALLBACK_STORE_SETTINGS;
    }

    const json = (await res.json()) as { data?: StoreSettingsApi };
    return resolveStoreSettings(json.data ?? null);
  } catch (e) {
    console.error("[ajustes] No se pudo leer la API; se usa el contacto de respaldo y sin redes.", e);
    return FALLBACK_STORE_SETTINGS;
  }
}

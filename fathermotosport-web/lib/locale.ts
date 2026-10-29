export type AppLocale = "es" | "pt" | "en";

export const APP_LOCALES: readonly AppLocale[] = ["es", "pt", "en"];

/**
 * Idioma de la página que se está viendo (el layout pone <html lang={locale}>). Se usa
 * fuera de React: en el cliente HTTP y antes de salir a Google. Fuera del navegador, "es".
 */
export function currentLocale(): AppLocale {
  if (typeof document === "undefined") return "es";
  const lang = document.documentElement.lang.slice(0, 2) as AppLocale;
  return APP_LOCALES.includes(lang) ? lang : "es";
}

/** Idioma con que se salió a Google: el callback vuelve sin prefijo de idioma. */
export const GOOGLE_LOCALE_KEY = "fms_google_locale";

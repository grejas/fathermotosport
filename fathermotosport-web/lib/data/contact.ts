/**
 * Respaldo del correo de contacto: se usa si la API de "Configuración de la tienda"
 * falla o el correo está vacío en el panel (ver lib/api/storeSettings.ts).
 */
export const CONTACT_EMAIL = "contacto@fathermotosport.com";

/**
 * WhatsApp de respaldo, solo dígitos (con código de país). Solo si la API falla: vacío
 * en el panel = sin enlace ni botón de WhatsApp.
 */
export const CONTACT_WHATSAPP = "59168736384";

export const CONTACT_EMAIL_HREF = `mailto:${CONTACT_EMAIL}`;

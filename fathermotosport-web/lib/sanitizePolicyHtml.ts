import "server-only";

import sanitizeHtml from "sanitize-html";

/**
 * Sanitiza en el servidor el HTML del RichEditor antes de insertarlo en la página.
 *
 * ProductDescription usa DOMPurify, pero solo funciona en el navegador; la política
 * se prerenderiza en el servidor (ISR), así que acá va sanitize-html con una lista
 * cerrada: solo lo que ofrece la barra del editor (títulos, listas, negritas,
 * enlaces). Todo lo demás (scripts, iframes, imágenes, estilos, on*) se descarta.
 */
export function sanitizePolicyHtml(html: string): string {
  return sanitizeHtml(html, {
    allowedTags: ["p", "div", "br", "h2", "h3", "h4", "strong", "b", "em", "i", "u", "s", "del", "ul", "ol", "li", "a", "blockquote"],
    allowedAttributes: { a: ["href", "target"] },
    allowedSchemes: ["http", "https", "mailto", "tel"],
    allowProtocolRelative: false,
    transformTags: {
      // La página ya tiene su <h1> (el título); un h1 del editor baja a h2.
      h1: "h2",
      a: (tagName, attribs) => ({
        tagName,
        attribs: {
          href: attribs.href ?? "",
          ...(attribs.target === "_blank" ? { target: "_blank" } : {}),
          rel: "noopener noreferrer",
        },
      }),
    },
  });
}

/** Texto plano del HTML (para la meta description, que Next vuelve a escapar). */
export function htmlToText(html: string): string {
  return sanitizeHtml(html.replace(/<\/(p|div|h\d|li)>/gi, " "), { allowedTags: [], allowedAttributes: {} })
    .replace(/&nbsp;/g, " ")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, "&")
    .replace(/\s+/g, " ")
    .trim();
}

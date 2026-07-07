"use client";

import { useMemo } from "react";
import DOMPurify from "dompurify";

/**
 * Renderiza la descripción HTML del producto (proveniente del RichEditor de
 * Filament) de forma segura: la sanitiza con DOMPurify y la muestra con estilos
 * tipográficos (prose) adaptados al tema oscuro Dark Race.
 */
export function ProductDescription({ html }: { html: string | null | undefined }) {
  const clean = useMemo(() => {
    if (!html || !html.trim()) return "";
    // DOMPurify usa el DOM del navegador; ProductTabs es un componente cliente
    // que solo se renderiza tras cargar los datos, por lo que window existe.
    if (typeof window === "undefined") return html;
    return DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });
  }, [html]);

  if (!clean) {
    return <p className="text-brand-muted">Sin descripción disponible.</p>;
  }

  return (
    <div
      className="prose prose-invert max-w-none prose-headings:text-brand-white prose-p:text-brand-muted prose-strong:text-brand-white prose-li:text-brand-muted prose-a:text-brand-red prose-a:no-underline hover:prose-a:underline"
      dangerouslySetInnerHTML={{ __html: clean }}
    />
  );
}

"use client";

import { useEffect, useState } from "react";
import { translateText } from "@/lib/utils/translate";

/** Traducciones ya pedidas en esta visita: el mismo texto no se pide dos veces. */
const cache = new Map<string, Promise<string>>();

/**
 * Texto cargado en español en el panel, traducido al vuelo al idioma activo. Mientras
 * llega la traducción (o si falla) se muestra el original, como el resto de la web.
 */
export function useTextoTraducido(texto: string | null | undefined, locale: string): string | null {
  const [traducido, setTraducido] = useState<string | null>(null);

  useEffect(() => {
    setTraducido(null);
    if (!texto || locale === "es") return;

    const clave = `${locale}\u0000${texto}`;
    if (!cache.has(clave)) cache.set(clave, translateText(texto, locale));

    let cancelado = false;
    cache.get(clave)!.then((t) => {
      if (!cancelado) setTraducido(t);
    });
    return () => {
      cancelado = true;
    };
  }, [texto, locale]);

  return texto ? traducido ?? texto : null;
}

"use client";

import { Fragment, useEffect, useState } from "react";
import { getProtectedNames, splitByProtectedNames } from "@/lib/utils/translate";

/**
 * Un texto libre (por ejemplo de un banner) con cada nombre de marca envuelto en
 * translate="no", para que tampoco lo traduzca el traductor del navegador. Hasta que
 * llega la lista de marcas se muestra el texto tal cual, igual que en el servidor.
 */
export function TextoConMarcas({ texto }: { texto: string }) {
  const [nombres, setNombres] = useState<string[]>([]);

  useEffect(() => {
    let cancelado = false;
    getProtectedNames().then((n) => {
      if (!cancelado) setNombres(n);
    });
    return () => {
      cancelado = true;
    };
  }, []);

  return (
    <>
      {splitByProtectedNames(texto, nombres).map((trozo, i) =>
        trozo.marca ? (
          <span key={i} translate="no">
            {trozo.texto}
          </span>
        ) : (
          <Fragment key={i}>{trozo.texto}</Fragment>
        )
      )}
    </>
  );
}

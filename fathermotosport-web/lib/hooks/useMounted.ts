"use client";

import { useEffect, useState } from "react";

/**
 * Devuelve `true` solo después del montaje en el cliente.
 * Útil para evitar errores de hidratación cuando un componente depende de
 * estado que difiere entre el servidor (vacío) y el cliente (localStorage),
 * como los stores de Zustand persistidos (carrito, auth, favoritos).
 */
export function useMounted(): boolean {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return mounted;
}

"use client";

import { useCallback, useEffect } from "react";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { getCartPrices } from "@/lib/api/cart";
import { useCartStore } from "@/store/cartStore";

/**
 * El carrito guarda el precio al agregar. Esto pide al servidor el precio final de hoy
 * (el mismo cálculo que el pedido) y lo aplica; si alguno cambió (venció un descuento,
 * empezó otro) avisa al cliente. Devuelve true si algo cambió.
 *
 * Sin red no cambia nada: el servidor igual recalcula el precio al crear el pedido.
 */
export function useSyncCartPrices() {
  const t = useTranslations("cart");

  return useCallback(async (): Promise<boolean> => {
    const { items, applyPrices } = useCartStore.getState();
    if (!items.length) return false;

    try {
      const cambiados = applyPrices(await getCartPrices(items.map((i) => i.variantId)));
      if (cambiados.length) {
        toast(t("prices_updated", { names: cambiados.join(", ") }), {
          id: "cart-prices",
          icon: "ℹ️",
          duration: 8000,
        });
        return true;
      }
    } catch {
      // Sin red o API caída: se sigue con lo guardado; el pedido lo valida el servidor.
    }

    return false;
  }, [t]);
}

/** Refresca los precios al mostrarse (carrito, checkout) y cuando cambian las variantes. */
export function useCartPricesOnShow(visible = true) {
  const sincronizar = useSyncCartPrices();
  const variantes = useCartStore((s) => s.items.map((i) => i.variantId).join(","));

  useEffect(() => {
    if (visible && variantes) void sincronizar();
  }, [visible, variantes, sincronizar]);
}

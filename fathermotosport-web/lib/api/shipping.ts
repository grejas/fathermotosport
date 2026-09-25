import apiClient from "./client";
import type { ShippingQuote } from "@/lib/types";

/**
 * Opciones de envío disponibles según país destino y peso del pedido. El backend
 * calcula el peso real de cada producto a partir de los items enviados; `weight_kg`
 * es el respaldo que se usa si el pedido no trae items.
 */
export async function calculateShipping(params: {
  countryCode: string;
  items: { variantId: string; quantity: number }[];
  weightKg: number;
}): Promise<ShippingQuote> {
  const { data } = await apiClient.get<ShippingQuote>("/shipping/calculate", {
    params: {
      country_code: params.countryCode,
      weight_kg: params.weightKg,
      items: params.items.map((i) => `${i.variantId}:${i.quantity}`).join(",") || undefined,
    },
  });
  return data;
}

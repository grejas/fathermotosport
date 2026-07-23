import apiClient from "./client";
import type { VisorColor } from "@/lib/types";

/**
 * Todos los colores de visor activos (catálogo global), ordenados por sort_order.
 */
export async function getVisorColors(): Promise<VisorColor[]> {
  const { data } = await apiClient.get<{ data: VisorColor[] }>("/visor-colors");
  return data.data;
}

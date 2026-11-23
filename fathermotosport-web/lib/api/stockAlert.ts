import apiClient from "./client";

export interface StockAlertInput {
  email: string;
  whatsapp?: string;
  size?: string;
  locale: string;
  /** Trampa para bots: siempre vacío. */
  website?: string;
}

export type StockAlertResult = { ok: true } | { ok: false; reason: "limited" | "invalid" | "network" };

/** Anota al cliente para avisarle cuando el producto agotado vuelva a tener stock. */
export async function requestStockAlert(slug: string, input: StockAlertInput): Promise<StockAlertResult> {
  try {
    await apiClient.post(`/products/${encodeURIComponent(slug)}/stock-alert`, input);
    return { ok: true };
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } }).response?.status;
    if (status === 429) return { ok: false, reason: "limited" };
    if (status === 422) return { ok: false, reason: "invalid" };
    return { ok: false, reason: "network" };
  }
}

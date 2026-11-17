import apiClient from "./client";

export interface PopupCoupon {
  status: "new" | "existing";
  code: string;
  percent: number;
  expires_at: string | null;
  minimum_amount: number;
}

export type PopupResult =
  | { ok: true; coupon: PopupCoupon }
  | { ok: false; reason: "already_used" | "limited" | "invalid" | "network" };

export interface PopupLeadInput {
  email: string;
  whatsapp?: string;
  country?: string;
  locale: string;
  consent: boolean;
  source?: Record<string, string>;
  /** Trampa para bots: siempre vacío. */
  website?: string;
}

/** Registra el email del popup y devuelve el cupón del cliente. */
export async function claimPopupCoupon(input: PopupLeadInput): Promise<PopupResult> {
  try {
    const { data } = await apiClient.post<PopupCoupon>("/popup/lead", input);
    return { ok: true, coupon: data };
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } }).response?.status;
    if (status === 409) return { ok: false, reason: "already_used" };
    if (status === 429) return { ok: false, reason: "limited" };
    if (status === 422) return { ok: false, reason: "invalid" };
    return { ok: false, reason: "network" };
  }
}

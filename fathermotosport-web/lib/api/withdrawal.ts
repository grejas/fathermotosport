import apiClient from "@/lib/api/client";

export interface WithdrawalInput {
  order_number: string;
  name: string;
  email: string;
  items?: string;
  reason?: string;
  locale: string;
  /** Trampa para bots: siempre vacío. */
  website?: string;
}

export interface WithdrawalResult {
  reference: string;
  received_at: string;
  email_sent: boolean;
}

/** Registra el desistimiento (POST /withdrawals); la API manda el acuse por correo. */
export async function sendWithdrawal(input: WithdrawalInput): Promise<WithdrawalResult> {
  const { data } = await apiClient.post<WithdrawalResult>("/withdrawals", input);
  return data;
}

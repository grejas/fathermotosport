import apiClient from "./client";

export interface UnsubscribeStatus {
  valid: boolean;
  /** Correo con parte oculta, ej. "ju••••••@gmail.com". */
  email?: string;
  unsubscribed?: boolean;
}

/** Consulta el enlace de baja (no da de baja). null si el enlace no es válido. */
export async function getUnsubscribeStatus(u: string, t: string): Promise<UnsubscribeStatus | null> {
  try {
    const { data } = await apiClient.get<UnsubscribeStatus>("/promos/unsubscribe", { params: { u, t } });
    return data;
  } catch {
    return null;
  }
}

/** Da de baja de las promociones (botón de confirmar de la página /baja). */
export async function confirmUnsubscribe(u: string, t: string): Promise<boolean> {
  try {
    await apiClient.post("/promos/unsubscribe", { u, t });
    return true;
  } catch {
    return false;
  }
}

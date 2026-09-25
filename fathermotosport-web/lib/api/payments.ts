import apiClient from "./client";
import type { Order } from "@/lib/types";

export interface PaypalCreateResponse {
  paypal_order_id: string;
  approval_url: string | null;
}

export interface PaypalCaptureResponse {
  status: string | null;
  order: Order | null;
}

/** Cabecera de autorización del pedido para el comprador invitado (sin cuenta). */
const orderAuth = (accessToken?: string) =>
  accessToken ? { headers: { "X-Order-Token": accessToken } } : undefined;

/**
 * Crea la orden en PayPal para un pedido ya creado y devuelve la URL de aprobación.
 * `accessToken` es el del pedido: obligatorio para comprar sin cuenta.
 */
export async function createPaypalOrder(
  orderId: string,
  accessToken?: string
): Promise<PaypalCreateResponse> {
  const { data } = await apiClient.post<PaypalCreateResponse>(
    "/payments/paypal/create",
    { order_id: orderId },
    orderAuth(accessToken)
  );
  return data;
}

/**
 * Captura el pago una vez que el cliente lo aprobó.
 * Recibe el id de la orden de PayPal (el `?token=` de la URL de retorno), no el del pedido.
 */
export async function capturePaypalOrder(
  paypalOrderId: string,
  accessToken?: string
): Promise<PaypalCaptureResponse> {
  const { data } = await apiClient.post<PaypalCaptureResponse>(
    `/payments/paypal/capture/${paypalOrderId}`,
    {},
    orderAuth(accessToken)
  );
  return data;
}

import apiClient from "./client";
import type { Order } from "@/lib/types";

export interface PaypalCreateResponse {
  paypal_order_id: string;
  approval_url: string | null;
}

/**
 * Estado del pago. `order` es un resumen: nunca trae dirección, teléfono ni el
 * access_token del pedido. Lo comparten PayPal y Stripe, porque el backend responde
 * con el mismo helper en los dos casos.
 */
export interface PaymentStatusResponse {
  status: string | null;
  order:
    | (Pick<Order, "id" | "order_number" | "payment_status" | "status" | "total"> & {
        /** true = se pagó sin cuenta, así que se puede ofrecer crearla. */
        is_guest?: boolean;
        customer_name?: string | null;
        customer_email?: string | null;
      })
    | null;
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
): Promise<PaymentStatusResponse> {
  const { data } = await apiClient.post<PaymentStatusResponse>(
    `/payments/paypal/capture/${paypalOrderId}`,
    {},
    orderAuth(accessToken)
  );
  return data;
}

export interface StripeIntentResponse {
  client_secret: string;
  payment_intent_id: string;
  /** true = se reutilizó el intent pendiente en vez de crear otro (evita cobro doble). */
  reused?: boolean;
}

/**
 * Crea (o reutiliza) el PaymentIntent de Stripe para un pedido ya creado y devuelve el
 * client_secret que necesita el Payment Element.
 */
export async function createStripeIntent(
  orderId: string,
  accessToken?: string
): Promise<StripeIntentResponse> {
  const { data } = await apiClient.post<StripeIntentResponse>(
    "/payments/stripe/intent",
    { order_id: orderId },
    orderAuth(accessToken)
  );
  return data;
}

/**
 * Confirma el pago contra el backend, que relee el estado desde Stripe.
 * El estado que informe el navegador no alcanza: la fuente de verdad es Stripe.
 */
export async function confirmStripePayment(
  paymentIntentId: string,
  accessToken?: string
): Promise<PaymentStatusResponse> {
  const { data } = await apiClient.post<PaymentStatusResponse>(
    "/payments/stripe/confirm",
    { payment_intent_id: paymentIntentId },
    orderAuth(accessToken)
  );
  return data;
}

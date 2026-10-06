import apiClient from "./client";

export interface UpsellVariant {
  id: string;
  size: string | null;
  stock: number;
}

export interface UpsellOfferItem {
  rule_id: number;
  discount_percent: number;
  /** Precio completo, como string con 2 decimales. */
  price: string;
  discounted_price: string;
  product: {
    id: string;
    name: string;
    slug: string;
    primary_image: string | null;
    variants: UpsellVariant[];
  };
}

export interface UpsellOrderResponse {
  message: string;
  order: {
    id: string;
    order_number: string;
    access_token: string | null;
    subtotal: string;
    discount: string;
    total: string;
  };
}

/** Cabecera de autorización del pedido para el comprador invitado (sin cuenta). */
const orderAuth = (accessToken?: string) =>
  accessToken ? { headers: { "X-Order-Token": accessToken } } : undefined;

/**
 * Ofertas de venta cruzada de un pedido ya pagado. Devuelve lista vacía si no hay
 * ninguna, si el pedido no está pagado, o si ya pagó una venta cruzada.
 */
export async function getUpsellOffers(
  orderId: string,
  accessToken?: string
): Promise<UpsellOfferItem[]> {
  const { data } = await apiClient.get<{ offers: UpsellOfferItem[] }>(
    `/orders/${orderId}/upsell`,
    orderAuth(accessToken)
  );
  return data.offers;
}

/**
 * Crea el pedido de venta cruzada con lo elegido. Si ya había uno pendiente, el backend
 * lo reutiliza reemplazando sus items, así reintentar no deja pedidos duplicados.
 */
export async function createUpsellOrder(
  orderId: string,
  items: { rule_id: number; variant_id: string }[],
  accessToken?: string
): Promise<UpsellOrderResponse> {
  const { data } = await apiClient.post<UpsellOrderResponse>(
    `/orders/${orderId}/upsell`,
    { items },
    orderAuth(accessToken)
  );
  return data;
}

/**
 * El cliente cerró el modal sin comprar: el servidor da la oferta por perdida y
 * cancela el pedido de venta cruzada que hubiera quedado sin pagar. Idempotente.
 */
export async function dismissUpsell(orderId: string, accessToken?: string): Promise<void> {
  await apiClient.post(`/orders/${orderId}/upsell/dismiss`, {}, orderAuth(accessToken));
}

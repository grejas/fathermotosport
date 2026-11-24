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
  /** Rebaja del último aviso (si la regla la tiene): porcentaje y precio final. */
  last_chance_percent: number | null;
  last_chance_price: string | null;
  /** Producto comprado que dispara la oferta, para el "Combina con tu …". */
  pairs_with: string | null;
  /** Lo ofrecido es del mismo tipo que lo comprado ("tu segundo casco"). */
  same_kind: boolean;
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

/** Tarjeta del pago original, para cobrar el upsell en un toque. */
export interface SavedCard {
  brand: string | null;
  last4: string | null;
}

export interface UpsellOffersResponse {
  offers: UpsellOfferItem[];
  /** El pago del pedido todavía no figura confirmado (3-D Secure): reintentar. */
  pending?: boolean;
  saved_card?: SavedCard | null;
}

/**
 * Ofertas de venta cruzada de un pedido ya pagado. Lista vacía si no hay ninguna o si
 * ya pagó una venta cruzada; `pending` si el pago aún no figura confirmado.
 */
export async function getUpsellOffers(
  orderId: string,
  accessToken?: string
): Promise<UpsellOffersResponse> {
  const { data } = await apiClient.get<UpsellOffersResponse>(
    `/orders/${orderId}/upsell`,
    orderAuth(accessToken)
  );
  return data;
}

/**
 * Crea el pedido de venta cruzada con lo elegido. Si ya había uno pendiente, el backend
 * lo reutiliza reemplazando sus items, así reintentar no deja pedidos duplicados.
 */
export async function createUpsellOrder(
  orderId: string,
  items: { rule_id: number; variant_id: string }[],
  accessToken?: string,
  /** Compra desde el último aviso: el servidor aplica la rebaja si la regla la tiene. */
  lastChance = false
): Promise<UpsellOrderResponse> {
  const { data } = await apiClient.post<UpsellOrderResponse>(
    `/orders/${orderId}/upsell`,
    lastChance ? { items, last_chance: true } : { items },
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

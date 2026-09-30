import apiClient from "./client";
import type { Order, PaginatedResponse, PaymentMethod } from "@/lib/types";

export interface CreateOrderPayload {
  guest_email?: string;
  items: { variant_id: string; quantity: number }[];
  address: {
    full_name: string;
    phone: string;
    country: string;
    state?: string;
    city: string;
    postal_code?: string;
    address_line: string;
    reference?: string;
  };
  payment_method: PaymentMethod;
  /** Opción de envío elegida; el backend revalida el precio contra su propia base. */
  shipping_option_id?: number;
  shipping_country_code?: string;
  coupon_code?: string;
  notes?: string;
}

/** PayPal Express: pedido creado desde el carrito, sin formulario previo. */
export interface CreateExpressOrderPayload {
  items: { variant_id: string; quantity: number }[];
  shipping_country_code: string;
  shipping_option_id: number;
}

export async function createExpressOrder(
  payload: CreateExpressOrderPayload
): Promise<{ message: string; order: Order }> {
  const { data } = await apiClient.post<{ message: string; order: Order }>(
    "/orders/paypal-express",
    payload
  );
  return data;
}

export interface CreateOrderResponse {
  message: string;
  order: Order;
  payment: { method: PaymentMethod; next_step: string };
}

export async function createOrder(payload: CreateOrderPayload): Promise<CreateOrderResponse> {
  const { data } = await apiClient.post<CreateOrderResponse>("/orders", payload);
  return data;
}

/**
 * Pedido por id. El comprador invitado pasa el access_token que recibió al comprar;
 * un usuario logueado no lo necesita (va por sesión).
 */
export async function getOrder(id: string, accessToken?: string): Promise<Order> {
  const { data } = await apiClient.get<{ data: Order }>(`/orders/${id}`, {
    headers: accessToken ? { "X-Order-Token": accessToken } : undefined,
  });
  return data.data;
}

export async function getMyOrders(): Promise<PaginatedResponse<Order>> {
  const { data } = await apiClient.get<PaginatedResponse<Order>>("/user/orders");
  return data;
}

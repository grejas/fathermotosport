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
  coupon_code?: string;
  notes?: string;
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

export async function getOrder(id: string): Promise<Order> {
  const { data } = await apiClient.get<{ data: Order }>(`/orders/${id}`);
  return data.data;
}

export async function getMyOrders(): Promise<PaginatedResponse<Order>> {
  const { data } = await apiClient.get<PaginatedResponse<Order>>("/user/orders");
  return data;
}

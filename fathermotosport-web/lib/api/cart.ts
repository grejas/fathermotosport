import apiClient from "./client";
import type { Cart } from "@/lib/types";

export async function getCart(): Promise<Cart> {
  const { data } = await apiClient.get<{ data: Cart }>("/cart");
  return data.data;
}

export async function addItem(variantId: string, quantity: number): Promise<Cart> {
  const { data } = await apiClient.post<{ cart: Cart }>("/cart/items", {
    variant_id: variantId,
    quantity,
  });
  return data.cart;
}

export async function updateItem(itemId: string, quantity: number): Promise<Cart> {
  const { data } = await apiClient.put<{ cart: Cart }>(`/cart/items/${itemId}`, { quantity });
  return data.cart;
}

export async function removeItem(itemId: string): Promise<Cart> {
  const { data } = await apiClient.delete<{ cart: Cart }>(`/cart/items/${itemId}`);
  return data.cart;
}

export async function clearCart(): Promise<void> {
  await apiClient.delete("/cart");
}

export async function mergeCart(): Promise<Cart> {
  const { data } = await apiClient.post<{ cart: Cart }>("/cart/merge");
  return data.cart;
}

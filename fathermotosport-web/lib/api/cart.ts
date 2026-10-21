import apiClient from "./client";
import type { Cart } from "@/lib/types";

/** Precio de hoy de una variante del carrito (POST /cart/prices). */
export interface CartPrice {
  variant_id: string;
  /** false: la talla o el producto ya no se venden. */
  available: boolean;
  stock: number;
  /** Precio de lista. */
  price: string | null;
  /** Lo que se cobra (el mismo cálculo que el pedido). */
  final_price: string | null;
  discount_percent: number;
  discount_source: "product" | "category" | null;
}

export async function getCartPrices(variantIds: string[]): Promise<CartPrice[]> {
  const { data } = await apiClient.post<{ data: CartPrice[] }>("/cart/prices", { variant_ids: variantIds });
  return data.data;
}

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

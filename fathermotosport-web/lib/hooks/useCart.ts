"use client";

import { useCartStore } from "@/store/cartStore";
import type { Product, ProductVariant } from "@/lib/types";
import toast from "react-hot-toast";

/** Acceso reactivo al carrito local (Zustand). */
export function useCart() {
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal);
  const itemCount = useCartStore((s) => s.itemCount);
  return { items, subtotal: subtotal(), itemCount: itemCount() };
}

export function useAddToCart() {
  const addItem = useCartStore((s) => s.addItem);
  return (product: Product, variant: ProductVariant, quantity = 1) => {
    if (!variant.in_stock || variant.stock < 1) {
      toast.error("Sin stock disponible.");
      return;
    }
    addItem(product, variant, quantity);
    toast.success(`${product.name} agregado al carrito`);
  };
}

export function useRemoveFromCart() {
  const removeItem = useCartStore((s) => s.removeItem);
  return (variantId: string) => {
    removeItem(variantId);
    toast.success("Producto eliminado");
  };
}

import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { Product, ProductVariant } from "@/lib/types";
import { SESSION_KEY } from "@/lib/api/client";

export interface LocalCartItem {
  variantId: string;
  productId: string;
  name: string;
  slug: string;
  image: string | null;
  color: string | null;
  size: string | null;
  price: number;
  quantity: number;
  stock: number;
  /** Peso del producto en kg. null = sin peso cargado (el envío asume DEFAULT_WEIGHT_KG). */
  weightKg: number | null;
}

/** Mismo valor que ShippingWeightService::DEFAULT_WEIGHT_KG en el backend. */
export const DEFAULT_WEIGHT_KG = 1;

/** Peso total del carrito en kg, asumiendo el valor por defecto donde falte. */
export function cartWeightKg(items: LocalCartItem[]): number {
  const total = items.reduce((sum, i) => sum + (i.weightKg ?? DEFAULT_WEIGHT_KG) * i.quantity, 0);
  return Math.round(total * 1000) / 1000;
}

interface CartState {
  items: LocalCartItem[];
  sessionId: string;
  isOpen: boolean;
  addItem: (product: Product, variant: ProductVariant, quantity?: number) => void;
  removeItem: (variantId: string) => void;
  updateQuantity: (variantId: string, quantity: number) => void;
  clearCart: () => void;
  openCart: () => void;
  closeCart: () => void;
  toggleCart: () => void;
  itemCount: () => number;
  subtotal: () => number;
  hasItem: (variantId: string) => boolean;
}

/** Genera (o recupera) un session id persistente para el carrito guest. */
function ensureSessionId(): string {
  if (typeof window === "undefined") return "";
  let id = localStorage.getItem(SESSION_KEY);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(SESSION_KEY, id);
  }
  return id;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      sessionId: "",
      isOpen: false,

      addItem: (product, variant, quantity = 1) => {
        const variantId = variant.id;
        // El precio es del producto (no varía por talla).
        const price = parseFloat(product.sale_price ?? product.price);
        const existing = get().items.find((i) => i.variantId === variantId);

        if (existing) {
          set({
            items: get().items.map((i) =>
              i.variantId === variantId
                ? { ...i, quantity: Math.min(i.quantity + quantity, variant.stock) }
                : i
            ),
          });
        } else {
          set({
            items: [
              ...get().items,
              {
                variantId,
                productId: product.id,
                name: product.name,
                slug: product.slug,
                image: product.primary_image,
                // El color es del producto, no algo que el cliente elige.
                color: product.color ?? null,
                size: variant.size,
                price,
                quantity: Math.min(quantity, variant.stock),
                stock: variant.stock,
                weightKg: product.weight ? parseFloat(product.weight) : null,
              },
            ],
          });
        }
        set({ isOpen: true });
      },

      removeItem: (variantId) =>
        set({ items: get().items.filter((i) => i.variantId !== variantId) }),

      updateQuantity: (variantId, quantity) =>
        set({
          items: get().items.map((i) =>
            i.variantId === variantId
              ? { ...i, quantity: Math.max(1, Math.min(quantity, i.stock)) }
              : i
          ),
        }),

      clearCart: () => set({ items: [] }),
      openCart: () => set({ isOpen: true }),
      closeCart: () => set({ isOpen: false }),
      toggleCart: () => set({ isOpen: !get().isOpen }),

      itemCount: () => get().items.reduce((sum, i) => sum + i.quantity, 0),
      subtotal: () => get().items.reduce((sum, i) => sum + i.price * i.quantity, 0),
      hasItem: (variantId) => get().items.some((i) => i.variantId === variantId),
    }),
    {
      name: "fms-cart",
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ items: state.items, sessionId: state.sessionId }),
      onRehydrateStorage: () => (state) => {
        if (state) state.sessionId = ensureSessionId();
      },
    }
  )
);

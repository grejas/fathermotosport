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
        const price = parseFloat(variant.price ?? product.sale_price ?? product.price);
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
                color: variant.color,
                size: variant.size,
                price,
                quantity: Math.min(quantity, variant.stock),
                stock: variant.stock,
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

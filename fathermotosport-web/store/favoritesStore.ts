import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { Product } from "@/lib/types";

interface FavoritesState {
  items: Product[];
  toggle: (product: Product) => void;
  isFavorite: (productId: string) => boolean;
  count: () => number;
  clear: () => void;
}

export const useFavoritesStore = create<FavoritesState>()(
  persist(
    (set, get) => ({
      items: [],

      toggle: (product) => {
        const exists = get().items.some((p) => p.id === product.id);
        set({
          items: exists
            ? get().items.filter((p) => p.id !== product.id)
            : [...get().items, product],
        });
      },

      isFavorite: (productId) => get().items.some((p) => p.id === productId),
      count: () => get().items.length,
      clear: () => set({ items: [] }),
    }),
    {
      name: "fms-favorites",
      storage: createJSONStorage(() => localStorage),
    }
  )
);

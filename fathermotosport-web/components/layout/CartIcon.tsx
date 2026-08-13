"use client";

import { ShoppingBag } from "lucide-react";
import { AnimatePresence, motion } from "framer-motion";
import { useCartStore } from "@/store/cartStore";
import { useMounted } from "@/lib/hooks/useMounted";

export function CartIcon() {
  const mounted = useMounted();
  const count = useCartStore((s) => s.itemCount());
  const openCart = useCartStore((s) => s.openCart);

  return (
    <button
      onClick={openCart}
      className="relative shrink-0 rounded-lg p-2 text-brand-white transition hover:bg-white/10"
      aria-label="Abrir carrito"
    >
      <ShoppingBag size={22} />
      <AnimatePresence>
        {mounted && count > 0 && (
          <motion.span
            key="cart-badge"
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            exit={{ scale: 0 }}
            className="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-brand-red px-1 text-[10px] font-bold text-white"
          >
            {count}
          </motion.span>
        )}
      </AnimatePresence>
    </button>
  );
}

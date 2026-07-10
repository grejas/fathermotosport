"use client";

import Link from "next/link";
import { ShoppingBag } from "lucide-react";
import { useCartStore } from "@/store/cartStore";
import { Drawer } from "@/components/ui/Drawer";
import { Button } from "@/components/ui/Button";
import { formatPrice } from "@/lib/utils";
import { CartItem } from "./CartItem";

export function CartDrawer() {
  const isOpen = useCartStore((s) => s.isOpen);
  const closeCart = useCartStore((s) => s.closeCart);
  const items = useCartStore((s) => s.items);
  const subtotal = useCartStore((s) => s.subtotal());

  return (
    <Drawer
      open={isOpen}
      onClose={closeCart}
      title={`Carrito (${items.length})`}
      footer={
        items.length > 0 ? (
          <div className="space-y-3">
            <div className="flex items-center justify-between text-sm">
              <span className="text-brand-muted">Subtotal</span>
              <span className="font-bold text-brand-white">{formatPrice(subtotal)}</span>
            </div>
            <p className="text-xs text-cat-boots">Envío gratis</p>
            <Link href="/cart" onClick={closeCart} className="block">
              <Button variant="glass" className="w-full">
                Ver carrito completo
              </Button>
            </Link>
            <Link href="/checkout" onClick={closeCart} className="block">
              <Button variant="primary" className="w-full">
                Ir al checkout
              </Button>
            </Link>
          </div>
        ) : undefined
      }
    >
      {items.length === 0 ? (
        <div className="flex h-full flex-col items-center justify-center gap-3 text-center text-brand-muted">
          <ShoppingBag size={48} className="opacity-40" />
          <p>Tu carrito está vacío</p>
          <Link href="/catalog" onClick={closeCart}>
            <Button variant="glass" size="sm">
              Explorar productos
            </Button>
          </Link>
        </div>
      ) : (
        items.map((item) => <CartItem key={item.variantId} item={item} />)
      )}
    </Drawer>
  );
}

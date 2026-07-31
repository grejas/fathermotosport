"use client";

import Link from "next/link";
import { useCartStore } from "@/store/cartStore";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { useMounted } from "@/lib/hooks/useMounted";

export default function CheckoutPage() {
  const mounted = useMounted();
  const items = useCartStore((s) => s.items);

  if (!mounted) {
    return (
      <div className="flex min-h-[70vh] items-center justify-center pt-20">
        <Spinner size={32} />
      </div>
    );
  }

  if (!items.length) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
        <h1 className="text-2xl font-bold text-brand-white">No hay productos para pagar</h1>
        <Link href="/catalog">
          <Button variant="primary">Ir al catálogo</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 pb-16 pt-24 sm:px-6">
      <h1 className="mb-8 text-3xl font-extrabold text-brand-white">Finalizar compra</h1>
      <CheckoutForm />
    </div>
  );
}

"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { motion } from "framer-motion";
import { Check } from "lucide-react";
import { Button } from "@/components/ui/Button";

export function SuccessClient() {
  const params = useSearchParams();
  const orderNumber = params.get("number") ?? "FMS-XXXX";

  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-4 pt-20 text-center">
      <motion.div
        initial={{ scale: 0, rotate: -20 }}
        animate={{ scale: 1, rotate: 0 }}
        transition={{ type: "spring", stiffness: 200, damping: 14 }}
        className="flex h-24 w-24 items-center justify-center rounded-full bg-cat-boots/15 ring-2 ring-cat-boots/40"
      >
        <Check size={48} className="text-cat-boots" strokeWidth={3} />
      </motion.div>

      <motion.h1
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="mt-6 text-3xl font-extrabold text-brand-white"
      >
        ¡Pedido confirmado!
      </motion.h1>

      <p className="mt-2 text-lg text-brand-red">{orderNumber}</p>
      <p className="mt-3 max-w-md text-brand-muted">
        Recibirás un email de confirmación con los detalles de tu compra. ¡Gracias por confiar en{" "}
        <span translate="no">FatherMotoSport</span>!
      </p>

      <div className="mt-8 flex gap-3">
        <Link href="/catalog">
          <Button variant="primary">Seguir comprando</Button>
        </Link>
        <Link href="/orders">
          <Button variant="glass">Ver mis pedidos</Button>
        </Link>
      </div>
    </div>
  );
}

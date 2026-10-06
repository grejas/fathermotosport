"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import { Check } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { UpsellOffer } from "@/components/checkout/UpsellOffer";
import { CreateAccountFromOrder } from "@/components/checkout/CreateAccountFromOrder";
import { getOrder } from "@/lib/api/orders";
import { useAuthStore } from "@/store/authStore";

/**
 * Consultas mientras el pago figura pendiente (el webhook de Stripe puede tardar):
 * 10 intentos cada 2 s, unos 20 s en total. Cuentan también las que fallan.
 */
const MAX_INTENTOS_PAGO = 10;
const INTERVALO_PAGO_MS = 2000;

export function SuccessClient() {
  const params = useSearchParams();
  const orderNumber = params.get("number") ?? "FMS-XXXX";
  // El id y el token ya venían en la URL; hacían falta para pedir la venta cruzada.
  const orderId = params.get("order");
  const accessToken = params.get("t") ?? undefined;
  const isAuth = useAuthStore((s) => s.isAuth);

  // Se lee el pedido para saber si es de invitado, con cualquier método de pago.
  // Solo hace falta para ofrecer la cuenta, así que sin token o con sesión ni se pide.
  const { data: pedido } = useQuery({
    queryKey: ["order", orderId],
    queryFn: () => getOrder(orderId!, accessToken),
    enabled: Boolean(orderId && accessToken && !isAuth),
    retry: false,
    // Si se llegó por la redirección de Stripe (3-D Secure) el pago puede seguir
    // pendiente unos segundos, y el backend solo deja crear la cuenta si está pagado.
    // Se corta si la consulta falla, si el pedido se canceló, o al agotar los intentos.
    refetchInterval: (query) => {
      const { data, status, dataUpdateCount, errorUpdateCount } = query.state;
      const sigueEsperando =
        status === "success" &&
        data?.payment_status === "pending" &&
        data.status !== "cancelled" &&
        dataUpdateCount + errorUpdateCount < MAX_INTENTOS_PAGO;
      return sigueEsperando ? INTERVALO_PAGO_MS : false;
    },
  });

  const ofrecerCuenta =
    !isAuth && accessToken && pedido?.is_guest && pedido.payment_status === "paid";

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

      {ofrecerCuenta && (
        <div className="w-full max-w-md">
          <CreateAccountFromOrder
            orderId={pedido.id}
            accessToken={accessToken}
            customerName={pedido.address?.full_name ?? null}
            customerEmail={pedido.guest_email}
          />
        </div>
      )}

      {/* Modal, en un portal: se abre solo un momento después, cuando el cliente ya
          vio que su compra salió bien. No ocupa lugar en la página ni tapa la cuenta. */}
      {orderId && (
        <UpsellOffer orderId={orderId} orderNumber={orderNumber} accessToken={accessToken} />
      )}
    </div>
  );
}

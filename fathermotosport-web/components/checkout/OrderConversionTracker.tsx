"use client";

import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { getOrder } from "@/lib/api/orders";
import { trackOrder } from "@/lib/analytics/events";
import { tiktokPixelEnabled } from "@/lib/analytics/tiktok";
import { metaPixelEnabled } from "@/lib/analytics/meta";
import { ga4Enabled } from "@/lib/analytics/ga4";

const algunaAnalitica = tiktokPixelEnabled || metaPixelEnabled || ga4Enabled;

/** Mientras el pago figure pendiente (webhook de Stripe): 10 consultas cada 3 s. */
const MAX_INTENTOS = 10;
const INTERVALO_MS = 3000;

/** Marca en el navegador que un evento ya se envió, para no repetirlo al recargar la página. */
function primeraVez(clave: string): boolean {
  try {
    if (localStorage.getItem(clave)) return false;
    localStorage.setItem(clave, "1");
    return true;
  } catch {
    return true;
  }
}

/**
 * Registra la conversión en TikTok, Meta y GA4 desde la pantalla de éxito, a la que llegan todos
 * los métodos de pago (tarjeta, PayPal, QR, transferencia):
 * - Pedido creado: el pedido existe.
 * - Compra: el pedido figura pagado. Los pagos manuales (QR, transferencia) se
 *   confirman después en el panel, así que esos no se registran acá.
 *
 * No muestra nada.
 */
export function OrderConversionTracker({
  orderId,
  accessToken,
  isAuth,
}: {
  orderId: string;
  accessToken?: string;
  isAuth: boolean;
}) {
  const { data: pedido } = useQuery({
    // Clave propia: la pantalla de éxito usa ["order", id] con otras condiciones.
    queryKey: ["order-conversion", orderId],
    queryFn: () => getOrder(orderId, accessToken),
    enabled: algunaAnalitica && Boolean(accessToken || isAuth),
    retry: false,
    refetchInterval: (query) => {
      const { data, status, dataUpdateCount, errorUpdateCount } = query.state;
      const esperarPago =
        status === "success" &&
        data?.payment_status === "pending" &&
        data.status !== "cancelled" &&
        dataUpdateCount + errorUpdateCount < MAX_INTENTOS;
      return esperarPago ? INTERVALO_MS : false;
    },
  });

  useEffect(() => {
    if (!pedido || pedido.status === "cancelled") return;
    if (primeraVez(`fms-px-order-${pedido.id}`)) trackOrder(pedido, "creado");
    if (pedido.payment_status === "paid" && primeraVez(`fms-px-paid-${pedido.id}`)) {
      trackOrder(pedido, "pagado");
    }
  }, [pedido]);

  return null;
}

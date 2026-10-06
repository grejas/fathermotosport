"use client";

import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { AlertTriangle, Check, MessageCircle } from "lucide-react";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { capturePaypalOrder } from "@/lib/api/payments";

const WHATSAPP_URL = "https://wa.me/59168736384";

type Estado = "capturando" | "ok" | "error";

/**
 * Página de retorno de PayPal. PayPal agrega ?token= (el id de su orden) y ?PayerID=,
 * y nuestra return_url ya trae ?order= (el id del pedido). Acá se captura el pago.
 */
export function PaypalSuccessClient() {
  const t = useTranslations("checkout");
  const router = useRouter();
  const params = useSearchParams();
  const paypalOrderId = params.get("token");
  const orderId = params.get("order");
  // Token del pedido: PayPal lo devuelve tal como lo pusimos en la URL de retorno.
  const accessToken = params.get("t") ?? undefined;

  const [estado, setEstado] = useState<Estado>("capturando");
  const [mensaje, setMensaje] = useState<string | null>(null);
  // Número de pedido (FMS-0001). Si no llega, se muestra el id como respaldo.
  const [orderNumber, setOrderNumber] = useState<string | null>(null);
  // StrictMode monta dos veces en desarrollo: sin esto se capturaría dos veces.
  const yaCapturado = useRef(false);

  useEffect(() => {
    if (!paypalOrderId) {
      setEstado("error");
      setMensaje(t("paypal_missing_token"));
      return;
    }
    if (yaCapturado.current) return;
    yaCapturado.current = true;

    capturePaypalOrder(paypalOrderId, accessToken)
      .then((res) => {
        if (res.status === "COMPLETED") {
          const number = res.order?.order_number ?? "";
          setOrderNumber(number || null);

          // Sin cuenta o con ella, sigue a la pantalla de éxito: ahí se le ofrece
          // crearla al invitado, igual que si hubiera pagado con tarjeta.
          setEstado("ok");
          router.replace(
            `/checkout/success?order=${orderId ?? res.order?.id ?? ""}&number=${number}` +
              (accessToken ? `&t=${accessToken}` : "")
          );
          return;
        }
        setEstado("error");
        setOrderNumber(res.order?.order_number ?? null);
        setMensaje(t("paypal_not_completed", { status: res.status ?? "—" }));
      })
      .catch((err: unknown) => {
        const data = (err as { response?: { data?: { message?: string; order_number?: string } } })
          .response?.data;
        setEstado("error");
        setOrderNumber(data?.order_number ?? null);
        setMensaje(data?.message ?? t("paypal_capture_error"));
      });
  }, [paypalOrderId, orderId, router, t]);

  if (estado === "capturando" || estado === "ok") {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
        <Spinner size={32} />
        <p className="text-sm text-brand-muted">{t("paypal_capturing")}</p>
      </div>
    );
  }

  return (
    <div className="mx-auto flex min-h-[70vh] max-w-lg flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-gold/10">
        <AlertTriangle size={32} className="text-brand-gold" />
      </span>
      <h1 className="text-2xl font-bold text-brand-white">{t("paypal_capture_failed_title")}</h1>
      <p className="text-sm text-brand-muted">{mensaje}</p>
      {(orderNumber || orderId) && (
        <p className="text-xs text-brand-muted">
          {t("paypal_order_reference", { id: orderNumber ?? orderId ?? "" })}
        </p>
      )}
      <div className="mt-2 flex flex-col gap-3 sm:flex-row">
        <a
          href={WHATSAPP_URL}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110"
        >
          <MessageCircle size={16} />
          {t("write_whatsapp")}
        </a>
        <Link href="/orders">
          <Button variant="glass" icon={<Check size={16} />}>
            {t("paypal_see_orders")}
          </Button>
        </Link>
      </div>
    </div>
  );
}

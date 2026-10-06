"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { CheckCircle2, CreditCard, ShieldCheck, XCircle } from "lucide-react";
import toast from "react-hot-toast";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { AVISO_PEDIDO_CANCELADO } from "@/components/cart/CartNotice";
import { PaypalButton } from "@/components/checkout/PaypalButton";
import { StripePaymentModal } from "@/components/checkout/StripePaymentModal";
import { getOrder } from "@/lib/api/orders";
import { createPaypalOrder, createStripeIntent } from "@/lib/api/payments";
import { ENABLED_PAYMENT_METHODS } from "@/lib/data/paymentMethods";
import { formatPrice } from "@/lib/utils";
import type { Order } from "@/lib/types";

const CONTACTO = "contacto@fathermotosport.com";

type Estado = "cargando" | "listo" | "pagado" | "no_encontrado";

/**
 * Retoma el pago de un pedido que quedó pendiente, con el token del pedido (sirve sin
 * cuenta). Usa los mismos endpoints y componentes que el checkout: el backend decide si
 * el pedido todavía se puede pagar, y reutiliza el intent o la orden de PayPal
 * pendientes en vez de crear un cobro nuevo.
 */
export function PayPendingClient({ id }: { id: string }) {
  const t = useTranslations("pay_pending");
  const tCheckout = useTranslations("checkout");
  const tCart = useTranslations("cart");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const token = useSearchParams().get("t") ?? undefined;

  const [order, setOrder] = useState<Order | null>(null);
  const [estado, setEstado] = useState<Estado>("cargando");
  const [pagando, setPagando] = useState<"paypal" | "stripe" | null>(null);
  const [stripeSecret, setStripeSecret] = useState<string | null>(null);

  /** Relee el pedido y decide qué mostrar. Un pedido cancelado lleva al carrito. */
  const cargar = useCallback(async () => {
    try {
      const pedido = await getOrder(id, token);

      if (pedido.status === "cancelled") {
        router.replace(`/cart?aviso=${AVISO_PEDIDO_CANCELADO}`);
        return;
      }

      setOrder(pedido);
      setEstado(pedido.payment_status === "paid" ? "pagado" : "listo");
    } catch {
      // 403 (token incorrecto) o 404: el enlace no sirve.
      setEstado("no_encontrado");
    }
  }, [id, token, router]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const exito = useMemo(
    () =>
      order
        ? `/checkout/success?order=${order.id}&number=${order.order_number}` + (token ? `&t=${token}` : "")
        : "/checkout/success",
    [order, token]
  );

  /** URL absoluta de respaldo para Stripe, si el método exige salir de la página. */
  const returnUrlStripe = useMemo(() => {
    if (typeof window === "undefined") return "";

    return window.location.origin + window.location.pathname.replace(/\/checkout\/pay\/.*$/, "") + exito;
  }, [exito]);

  /**
   * El pedido pudo cancelarse o pagarse mientras la página estaba abierta: el backend
   * responde 409 y se vuelve a leer el pedido para mostrar lo que corresponde.
   */
  const fallo = async (err: unknown) => {
    const respuesta = (err as { response?: { status?: number; data?: { message?: string } } }).response;

    if (respuesta?.status === 409) {
      await cargar();
      return;
    }

    toast.error(respuesta?.data?.message ?? t("payment_error"));
  };

  const pagarConPaypal = async () => {
    setPagando("paypal");
    try {
      const paypal = await createPaypalOrder(id, token);
      if (!paypal.approval_url) throw new Error(tCheckout("paypal_no_approval_url"));

      toast.success(tCheckout("redirecting_paypal"));
      window.location.href = paypal.approval_url;
    } catch (err) {
      await fallo(err);
      setPagando(null);
    }
  };

  const pagarConTarjeta = async () => {
    setPagando("stripe");
    try {
      const intent = await createStripeIntent(id, token);
      setStripeSecret(intent.client_secret);
    } catch (err) {
      await fallo(err);
    } finally {
      setPagando(null);
    }
  };

  if (estado === "cargando") {
    return (
      <div className="flex min-h-[70vh] items-center justify-center pt-20">
        <Spinner size={32} />
      </div>
    );
  }

  if (estado === "no_encontrado" || !order) {
    return (
      <Aviso
        icono={<XCircle size={32} className="text-brand-red" />}
        titulo={t("not_found_title")}
        texto={t("not_found_text", { email: CONTACTO })}
      >
        <Link href="/catalog">
          <Button variant="primary">{tCommon("explore_products")}</Button>
        </Link>
      </Aviso>
    );
  }

  if (estado === "pagado") {
    return (
      <Aviso
        icono={<CheckCircle2 size={32} className="text-cat-boots" />}
        titulo={t("paid_title")}
        texto={t("paid_text", { number: order.order_number })}
      >
        <Link href="/catalog">
          <Button variant="primary">{tCommon("explore_products")}</Button>
        </Link>
      </Aviso>
    );
  }

  const conPaypal = ENABLED_PAYMENT_METHODS.includes("paypal");
  const conTarjeta = ENABLED_PAYMENT_METHODS.includes("stripe");

  return (
    <div className="mx-auto max-w-lg px-4 pb-16 pt-24">
      <h1 className="text-2xl font-extrabold text-brand-white">{t("title")}</h1>
      <p className="mt-1 text-sm text-brand-muted">{t("order_number", { number: order.order_number })}</p>
      <p className="mt-4 text-sm text-brand-white/80">{t("text")}</p>

      <div className="mt-6 rounded-2xl border border-white/10 bg-brand-card p-5">
        <div className="space-y-3">
          {order.items?.map((item) => (
            <div key={item.id} className="flex items-start justify-between gap-3 text-sm">
              <div>
                <p className="text-brand-white">{item.variant?.product?.name ?? t("product")}</p>
                <p className="text-xs text-brand-muted">
                  {[item.size, item.color].filter(Boolean).join(" · ")} · x{item.quantity}
                </p>
              </div>
              <span className="shrink-0 text-brand-white">{formatPrice(item.subtotal)}</span>
            </div>
          ))}
        </div>

        <div className="mt-4 space-y-2 border-t border-white/10 pt-4 text-sm">
          <div className="flex justify-between text-brand-muted">
            <span>{tCart("subtotal")}</span>
            <span>{formatPrice(order.subtotal)}</span>
          </div>
          {parseFloat(order.discount) > 0 && (
            <div className="flex justify-between text-brand-muted">
              <span>{t("discount")}</span>
              <span>-{formatPrice(order.discount)}</span>
            </div>
          )}
          <div className="flex justify-between text-brand-muted">
            <span>{t("shipping")}</span>
            <span>{parseFloat(order.shipping) > 0 ? formatPrice(order.shipping) : t("free")}</span>
          </div>
          <div className="flex justify-between border-t border-white/10 pt-2 text-base font-bold text-brand-white">
            <span>{t("total")}</span>
            <span>{formatPrice(order.total)}</span>
          </div>
        </div>

        <div className="mt-5 space-y-3">
          {conPaypal && (
            <PaypalButton
              label={tCheckout("pay_with")}
              ariaLabel={tCheckout("pay_with_paypal")}
              loading={pagando === "paypal"}
              disabled={pagando !== null}
              onClick={pagarConPaypal}
            />
          )}
          {conTarjeta && (
            <Button
              type="button"
              variant="primary"
              className="w-full"
              icon={<CreditCard size={16} />}
              loading={pagando === "stripe"}
              disabled={pagando !== null}
              onClick={pagarConTarjeta}
            >
              {t("pay_card")}
            </Button>
          )}
        </div>
        <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
          <ShieldCheck size={14} className="text-cat-boots" /> {tCheckout("ssl_secure")}
        </p>
      </div>

      <p className="mt-6 text-center text-xs text-brand-muted">
        {t.rich("contact", {
          email: () => (
            <a href={`mailto:${CONTACTO}`} className="text-brand-red hover:brightness-125">
              {CONTACTO}
            </a>
          ),
        })}
      </p>

      {/* Cerrar el modal deja el pedido pendiente: se puede reintentar y el intent se
          reutiliza en vez de cobrar de nuevo. */}
      <StripePaymentModal
        open={stripeSecret !== null}
        clientSecret={stripeSecret}
        accessToken={token}
        returnUrl={returnUrlStripe}
        onClose={() => setStripeSecret(null)}
        onPaid={() => {
          setStripeSecret(null);
          toast.success(t("paid_title"));
          router.push(exito);
        }}
      />
    </div>
  );
}

function Aviso({
  icono,
  titulo,
  texto,
  children,
}: {
  icono: React.ReactNode;
  titulo: string;
  texto: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="mx-auto flex min-h-[70vh] max-w-lg flex-col items-center justify-center gap-4 px-4 pt-20 text-center">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white/5">{icono}</span>
      <h1 className="text-2xl font-bold text-brand-white">{titulo}</h1>
      <p className="text-sm text-brand-muted">{texto}</p>
      <div className="mt-2">{children}</div>
    </div>
  );
}

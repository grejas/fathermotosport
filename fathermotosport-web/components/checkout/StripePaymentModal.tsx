"use client";

import { useEffect, useRef, useState } from "react";
import { loadStripe, type Appearance, type Stripe } from "@stripe/stripe-js";
import {
  Elements,
  ExpressCheckoutElement,
  PaymentElement,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";
import type { StripeExpressCheckoutElementReadyEvent } from "@stripe/stripe-js";
import { useTranslations } from "next-intl";
import { ShieldCheck } from "lucide-react";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui";
import { confirmStripePayment, type PaymentStatusResponse, type StripeMode } from "@/lib/api/payments";

/**
 * El SDK se carga una sola vez a nivel de módulo, no en cada render: es lo que
 * recomienda Stripe para no reinyectar el script. Sin la clave publicable queda en
 * null y el modal lo dice en vez de romperse en silencio.
 */
const publishableKey = process.env.NEXT_PUBLIC_STRIPE_KEY;
export const stripePromise: Promise<Stripe | null> | null = publishableKey
  ? loadStripe(publishableKey)
  : null;

/** Colores de la marca, para que el formulario de Stripe no choque con el tema oscuro. */
const appearance: Appearance = {
  theme: "night",
  variables: {
    colorPrimary: "#E8001D",
    colorBackground: "#141414",
    colorText: "#F5F5F5",
    colorTextSecondary: "#888888",
    colorDanger: "#E8001D",
    borderRadius: "12px",
    fontFamily: "inherit",
  },
};

interface Props {
  open: boolean;
  /** Lo devuelve /payments/stripe/intent; sin él no se puede montar el formulario. */
  clientSecret: string | null;
  /** Token del pedido: obligatorio para pagar sin cuenta. */
  accessToken?: string;
  /** Respaldo para los métodos que sí exigen salir de la página. */
  returnUrl: string;
  onClose: () => void;
  onPaid: (result: PaymentStatusResponse) => void;
  /**
   * El pago guarda la tarjeta (pedido normal, para la oferta en un toque) y hay que
   * avisarlo. En la venta cruzada no se guarda nada: false.
   */
  guardaTarjeta?: boolean;
  /**
   * "card": solo el formulario de tarjeta (débito o crédito). "wallet": los botones de
   * Apple Pay, Google Pay y Amazon Pay. Separados cargan mucho más rápido que todo junto.
   */
  modo?: StripeMode;
  /**
   * Tarjeta precargada: con el monto (USD) el formulario se arma ANTES de que exista el
   * cobro, así el cliente ya puede escribir la tarjeta mientras se crea el pedido. El
   * client_secret llega después y se usa recién al pagar.
   */
  monto?: number | null;
  /** setup_future_usage del cobro creado, para que el formulario coincida con él. */
  setupFutureUsage?: "on_session" | "off_session" | null;
}

export function StripePaymentModal({
  open,
  clientSecret,
  accessToken,
  returnUrl,
  onClose,
  onPaid,
  guardaTarjeta = true,
  modo = "card",
  monto = null,
  setupFutureUsage,
}: Props) {
  const t = useTranslations("checkout");
  const precargado = modo === "card" && monto !== null && monto > 0;
  // Antes de que llegue el cobro se asume lo que pidió la web; después, lo que dice Stripe.
  const guardar = setupFutureUsage !== undefined ? setupFutureUsage : guardaTarjeta ? "on_session" : null;

  if (precargado && stripePromise) {
    return (
      <Modal open={open} onClose={onClose} title={t("stripe_title")}>
        <Elements
          stripe={stripePromise}
          options={{
            mode: "payment",
            amount: Math.round(monto * 100),
            currency: "usd",
            paymentMethodTypes: ["card"],
            ...(guardar ? { setupFutureUsage: guardar } : {}),
            appearance,
          }}
        >
          <StripeForm
            accessToken={accessToken}
            returnUrl={returnUrl}
            onPaid={onPaid}
            guardaTarjeta={guardaTarjeta}
            clientSecret={clientSecret}
          />
        </Elements>
      </Modal>
    );
  }

  return (
    <Modal open={open} onClose={onClose} title={modo === "wallet" ? t("wallet_title") : t("stripe_title")}>
      {!stripePromise ? (
        <p className="text-sm text-brand-muted">{t("stripe_unavailable")}</p>
      ) : !clientSecret ? (
        <p className="text-sm text-brand-muted">{t("stripe_preparing")}</p>
      ) : (
        <Elements stripe={stripePromise} options={{ clientSecret, appearance }}>
          {modo === "wallet" ? (
            <WalletForm accessToken={accessToken} returnUrl={returnUrl} onPaid={onPaid} guardaTarjeta={guardaTarjeta} />
          ) : (
            <StripeForm accessToken={accessToken} returnUrl={returnUrl} onPaid={onPaid} guardaTarjeta={guardaTarjeta} />
          )}
        </Elements>
      )}
    </Modal>
  );
}

/** Va dentro de <Elements> porque los hooks de Stripe necesitan ese contexto. */
function StripeForm({
  accessToken,
  returnUrl,
  onPaid,
  guardaTarjeta,
  clientSecret,
}: Pick<Props, "accessToken" | "returnUrl" | "onPaid" | "guardaTarjeta"> & {
  /** Solo con el formulario precargado: el cobro, que puede llegar después. */
  clientSecret?: string | null;
}) {
  const stripe = useStripe();
  const elements = useElements();
  const t = useTranslations("checkout");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const precargado = clientSecret !== undefined;

  // Si el cliente toca "Pagar" antes de que el cobro esté creado, se espera a que llegue.
  const secretRef = useRef(clientSecret ?? null);
  const esperando = useRef<((s: string) => void) | null>(null);
  useEffect(() => {
    secretRef.current = clientSecret ?? null;
    if (clientSecret && esperando.current) {
      esperando.current(clientSecret);
      esperando.current = null;
    }
  }, [clientSecret]);
  const secretListo = () =>
    secretRef.current
      ? Promise.resolve(secretRef.current)
      : new Promise<string>((resolve) => (esperando.current = resolve));

  // No depende de ningún evento de submit: se dispara desde el onClick del botón, así el
  // cobro no puede quedar atado a un formulario (ni al que lo envuelva).
  const pagar = async () => {
    if (!stripe || !elements) {
      // Antes esto era un return mudo: el botón no hacía nada y no quedaba rastro.
      console.error("[Stripe] SDK no listo al intentar pagar", {
        stripe: Boolean(stripe),
        elements: Boolean(elements),
      });
      setError(t("stripe_error"));

      return;
    }

    setLoading(true);
    setError(null);

    let secret: string | undefined;
    if (precargado) {
      // Precargado: primero se validan los datos de la tarjeta, después se espera el cobro.
      const { error: datosError } = await elements.submit();
      if (datosError) {
        setError(datosError.message ?? t("stripe_error"));
        setLoading(false);
        return;
      }
      secret = await secretListo();
    }

    // redirect: "if_required" cobra sin salir de la página. El return_url solo se usa
    // si el método elegido exige redirección (algunos wallets), y ahí Stripe se va sola.
    const { error: stripeError, paymentIntent } = await stripe.confirmPayment({
      elements,
      ...(secret ? { clientSecret: secret } : {}),
      confirmParams: { return_url: returnUrl },
      redirect: "if_required",
    });

    if (stripeError) {
      setError(stripeError.message ?? t("stripe_error"));
      setLoading(false);
      return;
    }

    if (!paymentIntent) {
      setError(t("stripe_error"));
      setLoading(false);
      return;
    }

    // Lo que diga el navegador no alcanza: el backend relee el intent desde Stripe y es
    // el que marca el pedido como pagado y descuenta el stock.
    try {
      const result = await confirmStripePayment(paymentIntent.id, accessToken);

      if (result.order?.payment_status === "paid") {
        onPaid(result);
        return;
      }

      setError(t("stripe_not_completed", { status: result.status ?? paymentIntent.status }));
    } catch (err: unknown) {
      const message = (err as { response?: { data?: { message?: string } } }).response?.data
        ?.message;
      setError(message ?? t("stripe_error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Billeteras primero: en Safari/iPhone sale Apple Pay, en Chrome Google Pay, y
          Amazon Pay donde aplique. Stripe solo muestra las que el dispositivo admite. */}
      {/* Solo tarjeta: las billeteras tienen su propio recuadro en el checkout. Sin
          ellas el formulario no consulta Apple Pay / Google Pay y aparece al instante. */}
      <PaymentElement
        options={{
          layout: { type: "tabs", defaultCollapsed: false },
          paymentMethodOrder: ["card"],
          wallets: { applePay: "never", googlePay: "never" },
        }}
      />

      {error && (
        <p className="rounded-xl border border-brand-red/40 bg-brand-red/10 p-3 text-sm text-brand-white">
          {error}
        </p>
      )}

      <Button type="button" variant="primary" className="w-full" loading={loading} onClick={pagar}>
        {t("stripe_pay")}
      </Button>

      <p className="flex items-center justify-center gap-1.5 text-xs text-brand-muted">
        <ShieldCheck size={14} className="text-cat-boots" /> {t("ssl_secure")}
      </p>

      {/* Aviso obligatorio al guardar la tarjeta: se usa solo para la oferta posterior a
          la compra, con el cliente presente y tocando él el botón. */}
      {guardaTarjeta && (
        <p className="text-center text-[11px] leading-snug text-brand-muted">{t("stripe_save_card")}</p>
      )}
    </div>
  );
}

/**
 * Billeteras: los botones de Apple Pay, Google Pay y Amazon Pay (Express Checkout de
 * Stripe). Un toque y Face ID / huella; el cobro se confirma igual que con tarjeta.
 */
function WalletForm({
  accessToken,
  returnUrl,
  onPaid,
  guardaTarjeta,
}: Pick<Props, "accessToken" | "returnUrl" | "onPaid" | "guardaTarjeta">) {
  const stripe = useStripe();
  const elements = useElements();
  const t = useTranslations("checkout");
  const [estado, setEstado] = useState<"cargando" | "listo" | "sin_billetera">("cargando");
  const [error, setError] = useState<string | null>(null);

  const alEstarListo = (e: StripeExpressCheckoutElementReadyEvent) => {
    // Sin ninguna billetera en este dispositivo/navegador, se dice claro y se sugiere tarjeta.
    setEstado(e.availablePaymentMethods ? "listo" : "sin_billetera");
  };

  const confirmar = async () => {
    if (!stripe || !elements) {
      setError(t("stripe_error"));
      return;
    }
    setError(null);

    const { error: stripeError, paymentIntent } = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: returnUrl },
      redirect: "if_required",
    });

    if (stripeError || !paymentIntent) {
      setError(stripeError?.message ?? t("stripe_error"));
      return;
    }

    try {
      const result = await confirmStripePayment(paymentIntent.id, accessToken);
      if (result.order?.payment_status === "paid") {
        onPaid(result);
        return;
      }
      setError(t("stripe_not_completed", { status: result.status ?? paymentIntent.status }));
    } catch (err: unknown) {
      const message = (err as { response?: { data?: { message?: string } } }).response?.data?.message;
      setError(message ?? t("stripe_error"));
    }
  };

  return (
    <div className="space-y-4">
      {estado === "cargando" && <p className="text-sm text-brand-muted">{t("stripe_preparing")}</p>}

      {estado === "sin_billetera" && (
        <p className="rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-brand-white">
          {t("wallet_unavailable")}
        </p>
      )}

      <ExpressCheckoutElement
        onReady={alEstarListo}
        onConfirm={() => void confirmar()}
        options={{
          paymentMethodOrder: ["apple_pay", "google_pay", "amazon_pay"],
          // Solo billeteras: PayPal y Link no van en este recuadro.
          paymentMethods: { applePay: "always", googlePay: "always", amazonPay: "auto", link: "never", paypal: "never" },
          buttonHeight: 48,
          buttonType: { applePay: "buy", googlePay: "buy" },
        }}
      />

      {error && (
        <p className="rounded-xl border border-brand-red/40 bg-brand-red/10 p-3 text-sm text-brand-white">{error}</p>
      )}

      <p className="flex items-center justify-center gap-1.5 text-xs text-brand-muted">
        <ShieldCheck size={14} className="text-cat-boots" /> {t("ssl_secure")}
      </p>

      {guardaTarjeta && (
        <p className="text-center text-[11px] leading-snug text-brand-muted">{t("stripe_save_card")}</p>
      )}
    </div>
  );
}

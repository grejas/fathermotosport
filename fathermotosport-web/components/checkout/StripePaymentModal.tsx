"use client";

import { useState } from "react";
import { loadStripe, type Appearance, type Stripe } from "@stripe/stripe-js";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";
import { useTranslations } from "next-intl";
import { ShieldCheck } from "lucide-react";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui";
import { confirmStripePayment, type PaymentStatusResponse } from "@/lib/api/payments";

/**
 * El SDK se carga una sola vez a nivel de módulo, no en cada render: es lo que
 * recomienda Stripe para no reinyectar el script. Sin la clave publicable queda en
 * null y el modal lo dice en vez de romperse en silencio.
 */
const publishableKey = process.env.NEXT_PUBLIC_STRIPE_KEY;
const stripePromise: Promise<Stripe | null> | null = publishableKey
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
}

export function StripePaymentModal({
  open,
  clientSecret,
  accessToken,
  returnUrl,
  onClose,
  onPaid,
}: Props) {
  const t = useTranslations("checkout");

  return (
    <Modal open={open} onClose={onClose} title={t("stripe_title")}>
      {!stripePromise ? (
        <p className="text-sm text-brand-muted">{t("stripe_unavailable")}</p>
      ) : !clientSecret ? (
        <p className="text-sm text-brand-muted">{t("stripe_preparing")}</p>
      ) : (
        <Elements stripe={stripePromise} options={{ clientSecret, appearance }}>
          <StripeForm accessToken={accessToken} returnUrl={returnUrl} onPaid={onPaid} />
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
}: Pick<Props, "accessToken" | "returnUrl" | "onPaid">) {
  const stripe = useStripe();
  const elements = useElements();
  const t = useTranslations("checkout");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

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

    // redirect: "if_required" cobra sin salir de la página. El return_url solo se usa
    // si el método elegido exige redirección (algunos wallets), y ahí Stripe se va sola.
    const { error: stripeError, paymentIntent } = await stripe.confirmPayment({
      elements,
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
      <PaymentElement />

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
    </div>
  );
}

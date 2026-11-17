import type { ManualPaymentMethod, PaymentMethod } from "@/lib/types";

/**
 * Métodos de pago habilitados. Los que no estén acá se muestran en gris
 * ("Próximamente") y el checkout ofrece coordinar el pago por WhatsApp.
 *
 * Vive en un módulo aparte porque lo consumen el checkout y la venta cruzada
 * post-compra: duplicar la lista haría que deshabilitar una pasarela en un lado la
 * dejara activa en el otro.
 */
export const ENABLED_PAYMENT_METHODS: readonly PaymentMethod[] = ["paypal", "stripe"];

/**
 * Transferencia SEPA y cripto. Se ofrecen solo si el panel tiene sus datos completos
 * (StoreSettings.manualPaymentMethods); el pago lo confirma una persona.
 */
export const MANUAL_PAYMENT_METHODS: readonly ManualPaymentMethod[] = ["sepa", "usdt_trc20", "usdt_bep20", "btc"];

export const CRYPTO_PAYMENT_METHODS: readonly ManualPaymentMethod[] = ["usdt_trc20", "usdt_bep20", "btc"];

export function isManualPaymentMethod(method: string | null | undefined): method is ManualPaymentMethod {
  return MANUAL_PAYMENT_METHODS.includes(method as ManualPaymentMethod);
}

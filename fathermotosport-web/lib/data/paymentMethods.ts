import type { ManualPaymentMethod, PaymentMethod } from "@/lib/types";

/**
 * Métodos de pago habilitados. Los que no estén acá se muestran en gris
 * ("Próximamente") y el checkout ofrece coordinar el pago por WhatsApp.
 *
 * Vive en un módulo aparte porque lo consumen el checkout y la venta cruzada
 * post-compra: duplicar la lista haría que deshabilitar una pasarela en un lado la
 * dejara activa en el otro.
 */
// PayPal oculto (24-nov-2026): la cuenta conectada es boliviana y PayPal rechaza todos los
// cobros con PAYEE_ACCOUNT_RESTRICTED. Volver a agregar "paypal" cuando estén cargadas las
// credenciales de la PayPal de FATHERSON IMP LLC.
// Billeteras (Apple Pay, Google Pay, Amazon Pay) y tarjeta van en recuadros separados,
// las dos por Stripe.
export const ENABLED_PAYMENT_METHODS: readonly PaymentMethod[] = ["stripe_wallet", "stripe"];

/**
 * Transferencia SEPA, transferencia en EE.UU. (ACH / wire, cuenta Mercury) y cripto. Se ofrecen solo si el panel tiene sus datos completos
 * (StoreSettings.manualPaymentMethods); el pago lo confirma una persona.
 */
export const MANUAL_PAYMENT_METHODS: readonly ManualPaymentMethod[] = ["sepa", "us_bank", "binance_pay", "usdt_trc20", "usdt_bep20", "btc", "btc_bep20"];

export const CRYPTO_PAYMENT_METHODS: readonly ManualPaymentMethod[] = ["binance_pay", "usdt_trc20", "usdt_bep20", "btc", "btc_bep20"];

export function isManualPaymentMethod(method: string | null | undefined): method is ManualPaymentMethod {
  return MANUAL_PAYMENT_METHODS.includes(method as ManualPaymentMethod);
}

/**
 * ¿Se puede anunciar esta pasarela en la portada y el footer? Solo si el checkout la
 * ofrece de verdad: así, al apagar PayPal o MercadoPago, desaparecen también de la vitrina
 * (antes la portada seguía mostrando PayPal y el cliente lo buscaba en vano en el pago).
 */
export function isGatewayAdvertised(method: "paypal" | "mercadopago"): boolean {
  return ENABLED_PAYMENT_METHODS.includes(method);
}

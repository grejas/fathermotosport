import type { PaymentMethod } from "@/lib/types";

/**
 * Métodos de pago habilitados. Los que no estén acá se muestran en gris
 * ("Próximamente") y el checkout ofrece coordinar el pago por WhatsApp.
 *
 * Vive en un módulo aparte porque lo consumen el checkout y la venta cruzada
 * post-compra: duplicar la lista haría que deshabilitar una pasarela en un lado la
 * dejara activa en el otro.
 */
export const ENABLED_PAYMENT_METHODS: readonly PaymentMethod[] = ["paypal", "stripe"];

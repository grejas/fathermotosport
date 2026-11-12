/**
 * Eventos del embudo de compra, en un solo lugar.
 *
 * Los componentes llaman a estas funciones y no a un píxel concreto. Cada función envía el
 * mismo hecho a TikTok, Meta y GA4, con el nombre y el formato que pide cada plataforma.
 * Una plataforma sin ID configurado simplemente no recibe nada.
 *
 * Todos los precios del sitio están en USD.
 */
import type { Order, Product } from "@/lib/types";
import type { LocalCartItem } from "@/store/cartStore";
import { finalPrice } from "@/lib/utils";
import { tiktokIdentify, tiktokTrack, type TikTokContent } from "@/lib/analytics/tiktok";
import { metaTrack, metaTrackCustom, type MetaContent } from "@/lib/analytics/meta";
import { ga4Event, type Ga4Item } from "@/lib/analytics/ga4";

const CURRENCY = "USD";

const redondear = (n: number) => Math.round(n * 100) / 100;

/** Una línea de compra, neutral: de acá sale el formato de cada plataforma. */
interface Linea {
  id: string;
  name?: string;
  price: number;
  quantity: number;
}

const aTikTok = (l: Linea[]): TikTokContent[] =>
  l.map((x) => ({ content_id: x.id, content_type: "product", content_name: x.name, quantity: x.quantity, price: x.price }));

const aMeta = (l: Linea[]): MetaContent[] => l.map((x) => ({ id: x.id, quantity: x.quantity, item_price: x.price }));

const aGa4 = (l: Linea[]): Ga4Item[] =>
  l.map((x) => ({ item_id: x.id, item_name: x.name, price: x.price, quantity: x.quantity }));

const total = (l: Linea[]) => redondear(l.reduce((s, x) => s + x.price * x.quantity, 0));
const unidades = (l: Linea[]) => l.reduce((s, x) => s + x.quantity, 0);

function desdeProducto(product: Product, quantity: number): Linea[] {
  return [{ id: product.id, name: product.name, price: redondear(finalPrice(product)), quantity }];
}

function desdeCarrito(items: LocalCartItem[]): Linea[] {
  return items.map((i) => ({ id: i.productId, name: i.name, price: redondear(i.price), quantity: i.quantity }));
}

function desdePedido(order: Order): Linea[] {
  return (order.items ?? []).map((i) => ({
    id: i.variant?.product?.id ?? i.product_variant_id,
    name: i.variant?.product?.name,
    price: redondear(parseFloat(i.unit_price)),
    quantity: i.quantity,
  }));
}

/** El mismo hecho, a las tres plataformas. */
function enviar(
  lineas: Linea[],
  valor: number,
  nombres: { tiktok: Parameters<typeof tiktokTrack>[0]; meta: Parameters<typeof metaTrack>[0]; ga4: string },
  extra: { metaExtra?: Record<string, unknown>; ga4Extra?: Record<string, unknown>; tiktokDesc?: string } = {}
): void {
  tiktokTrack(nombres.tiktok, {
    contents: aTikTok(lineas),
    value: valor,
    currency: CURRENCY,
    ...(extra.tiktokDesc ? { description: extra.tiktokDesc } : {}),
  });
  metaTrack(nombres.meta, {
    content_ids: lineas.map((l) => l.id),
    content_type: "product",
    contents: aMeta(lineas),
    num_items: unidades(lineas),
    value: valor,
    currency: CURRENCY,
    ...extra.metaExtra,
  });
  ga4Event(nombres.ga4, { currency: CURRENCY, value: valor, items: aGa4(lineas), ...extra.ga4Extra });
}

/** Ficha de producto abierta. */
export function trackViewContent(product: Product): void {
  const l = desdeProducto(product, 1);
  enviar(l, total(l), { tiktok: "ViewContent", meta: "ViewContent", ga4: "view_item" }, {
    metaExtra: { content_name: product.name },
  });
}

/** Producto agregado al carrito (desde la ficha, una tarjeta del catálogo o favoritos). */
export function trackAddToCart(product: Product, quantity = 1): void {
  const l = desdeProducto(product, quantity);
  enviar(l, total(l), { tiktok: "AddToCart", meta: "AddToCart", ga4: "add_to_cart" }, {
    metaExtra: { content_name: product.name },
  });
}

/** El cliente abrió el checkout con productos en el carrito. */
export function trackInitiateCheckout(items: LocalCartItem[]): void {
  if (!items.length) return;
  const l = desdeCarrito(items);
  enviar(l, total(l), { tiktok: "InitiateCheckout", meta: "InitiateCheckout", ga4: "begin_checkout" });
}

/** Eligió método de pago y confirmó. `method`: paypal, stripe, qr, etc. */
export function trackAddPaymentInfo(items: LocalCartItem[], method: string): void {
  if (!items.length) return;
  const l = desdeCarrito(items);
  enviar(l, total(l), { tiktok: "AddPaymentInfo", meta: "AddPaymentInfo", ga4: "add_payment_info" }, {
    tiktokDesc: method,
    metaExtra: { payment_method: method },
    ga4Extra: { payment_type: method },
  });
}

/** Email y teléfono del comprador (solo TikTok, y solo como hash). */
export function identifyCustomer(email?: string | null, phone?: string | null): void {
  void tiktokIdentify(email, phone);
}

/**
 * Pedido creado (aún puede estar sin pagar: QR, transferencia) y pago confirmado.
 * El id del pedido viaja como event_id para que la plataforma no lo cuente dos veces si más
 * adelante también se envía desde el servidor.
 *
 * - Creado: TikTok PlaceAnOrder · Meta "PlaceOrder" (propio) · GA4 "place_order" (propio).
 * - Pagado: TikTok Purchase · Meta Purchase · GA4 purchase. Solo esta es la venta.
 */
export function trackOrder(order: Order, etapa: "creado" | "pagado"): void {
  const l = desdePedido(order);
  const valor = redondear(parseFloat(order.total));
  const base = { value: valor, currency: CURRENCY };

  if (etapa === "creado") {
    tiktokTrack("PlaceAnOrder", { contents: aTikTok(l), ...base }, `order-${order.id}`);
    metaTrackCustom(
      "PlaceOrder",
      { content_ids: l.map((x) => x.id), content_type: "product", contents: aMeta(l), num_items: unidades(l), ...base },
      `order-${order.id}`
    );
    ga4Event("place_order", { ...base, transaction_id: order.order_number, items: aGa4(l) });
    return;
  }

  tiktokTrack("Purchase", { contents: aTikTok(l), ...base }, `pay-${order.id}`);
  metaTrack(
    "Purchase",
    { content_ids: l.map((x) => x.id), content_type: "product", contents: aMeta(l), num_items: unidades(l), ...base },
    `pay-${order.id}`
  );
  ga4Event("purchase", {
    ...base,
    transaction_id: order.order_number,
    shipping: redondear(parseFloat(order.shipping || "0")),
    items: aGa4(l),
  });
}

/** Clic en un botón de WhatsApp. `origen`: product, footer, checkout, etc. */
export function trackWhatsappClick(origen: string, product?: Pick<Product, "id" | "name">): void {
  const fuente = `whatsapp_${origen}`;
  tiktokTrack("Contact", {
    description: fuente,
    ...(product
      ? { contents: [{ content_id: product.id, content_type: "product" as const, content_name: product.name }] }
      : {}),
  });
  metaTrack("Contact", {
    content_name: fuente,
    ...(product ? { content_ids: [product.id], content_type: "product" as const } : {}),
  });
  ga4Event("whatsapp_click", { source: origen, ...(product ? { item_id: product.id, item_name: product.name } : {}) });
}

/**
 * Dejó su email en el popup a cambio de un cupón. `percent` es el descuento que le tocó.
 * TikTok y Meta: Lead · GA4: generate_lead.
 */
export function trackLead(percent: number, email?: string, phone?: string): void {
  void tiktokIdentify(email, phone);
  tiktokTrack("Lead", { description: `popup_${percent}` });
  metaTrack("Lead", { content_name: `popup_${percent}` });
  ga4Event("generate_lead", { source: "popup", coupon_percent: percent });
}

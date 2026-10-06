"use client";

import { useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import Image from "next/image";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { Check, ChevronLeft, ChevronRight, Truck, X } from "lucide-react";
import toast from "react-hot-toast";
import { Button } from "@/components/ui/Button";
import { StripePaymentModal } from "./StripePaymentModal";
import { PaypalButton } from "./PaypalButton";
import {
  getUpsellOffers,
  createUpsellOrder,
  dismissUpsell,
  type UpsellOfferItem,
} from "@/lib/api/upsell";
import { createStripeIntent, createPaypalOrder } from "@/lib/api/payments";
import { ENABLED_PAYMENT_METHODS } from "@/lib/data/paymentMethods";
import { trackUpsell } from "@/lib/analytics/upsellEvents";
import { cn, formatPrice, getImageUrl } from "@/lib/utils";

interface Props {
  orderId: string;
  /** Número visible del pedido original (FMS-0001): el cliente lo reconoce. */
  orderNumber: string;
  /** Token del pedido original: así funciona para quien compró sin cuenta. */
  accessToken?: string;
}

/** Pedido de venta cruzada ya creado, esperando el pago. */
interface PedidoUpsell {
  id: string;
  order_number: string;
  access_token: string | null;
  total: string;
}

/**
 * oferta → pantalla principal (y el cobro, si ya eligió).
 * confirmar1 / confirmar2 → los dos avisos antes de perder la oferta.
 * agregado → pagó: se agradece y se cierra solo.
 */
type Paso = "oferta" | "confirmar1" | "confirmar2" | "agregado";

/** Cuánto esperar tras la confirmación antes de abrir: primero se ve que la compra salió bien. */
const DEMORA_APERTURA_MS = 1500;
const DURACION_AGREGADO_MS = 2600;

const SELECTOR_ENFOCABLES =
  'a[href], button:not([disabled]), select:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';

const ahorroDe = (o: UpsellOfferItem) => parseFloat(o.price) - parseFloat(o.discounted_price);

const mensajeDelError = (err: unknown) =>
  (err as { response?: { data?: { message?: string } } }).response?.data?.message;

const estadoDelError = (err: unknown) => (err as { response?: { status?: number } }).response?.status;

/**
 * Venta cruzada post-compra, en un modal que se abre solo tras confirmar el pedido.
 *
 * La oferta existe solo mientras el modal está abierto: al cerrarlo (tras dos avisos)
 * se avisa al servidor, que deja de ofrecerla y no deja comprarla. Al aceptar se crea
 * un pedido APARTE, vinculado al original y sin envío, que se paga con las mismas
 * pasarelas; el backend copia la dirección, así que no hay formulario.
 */
export function UpsellOffer({ orderId, orderNumber, accessToken }: Props) {
  const t = useTranslations("upsell");
  const reducirMovimiento = useReducedMotion();
  const tituloId = useId();

  const [montado, setMontado] = useState(false);
  // Se cerró en esta visita de la página. Solo en memoria: al recargar decide el
  // servidor, que es el único que sabe si la oferta sigue en pie.
  const [omitir, setOmitir] = useState(false);
  const [abierto, setAbierto] = useState(false);
  const [paso, setPaso] = useState<Paso>("oferta");
  const [indice, setIndice] = useState(0);
  // Nada preseleccionado: el cliente elige qué agregar.
  const [elegidos, setElegidos] = useState<Set<number>>(new Set());
  // Talla por oferta. Arranca en la primera disponible, pero no implica elegirla.
  const [tallas, setTallas] = useState<Record<number, string>>({});
  const [creando, setCreando] = useState(false);
  const [pedido, setPedido] = useState<PedidoUpsell | null>(null);
  const [stripeSecret, setStripeSecret] = useState<string | null>(null);

  const panelRef = useRef<HTMLDivElement>(null);
  const focoPrevio = useRef<HTMLElement | null>(null);
  const toqueInicio = useRef<number | null>(null);

  useEffect(() => setMontado(true), []);

  const { data: ofertas } = useQuery({
    queryKey: ["upsell", orderId],
    queryFn: () => getUpsellOffers(orderId, accessToken),
    // Un pedido sin ofertas es lo normal: no tiene sentido reintentar.
    retry: false,
    enabled: montado && !omitir,
    // Una vez abierto, no se vuelve a pedir solo: el modal trabaja con lo que mostró.
    staleTime: Infinity,
    refetchOnWindowFocus: false,
  });

  const lista = useMemo(() => ofertas ?? [], [ofertas]);
  const actual = lista[Math.min(indice, Math.max(lista.length - 1, 0))];

  // Se abre solo, un momento después de la confirmación, y solo si hay algo que ofrecer.
  useEffect(() => {
    if (omitir || lista.length === 0) return;
    const id = window.setTimeout(() => {
      setAbierto(true);
      trackUpsell("modal_visto", { orderId, ofertas: lista.length });
    }, DEMORA_APERTURA_MS);
    return () => window.clearTimeout(id);
  }, [omitir, lista.length, orderId]);

  const seleccionados = useMemo(() => lista.filter((o) => elegidos.has(o.rule_id)), [lista, elegidos]);
  const total = seleccionados.reduce((suma, o) => suma + parseFloat(o.discounted_price), 0);
  const ahorroTotal = seleccionados.reduce((suma, o) => suma + ahorroDe(o), 0);

  const tallaDe = (o: UpsellOfferItem) => tallas[o.rule_id] ?? o.product.variants[0]?.id ?? "";

  const alternar = (o: UpsellOfferItem) =>
    setElegidos((previos) => {
      const copia = new Set(previos);
      if (copia.has(o.rule_id)) copia.delete(o.rule_id);
      else copia.add(o.rule_id);
      return copia;
    });

  /** Cierra sin llamar al servidor: compra hecha, oferta vencida o ya descartada. */
  const cerrarDelTodo = useCallback(() => {
    setAbierto(false);
    setOmitir(true);
  }, []);

  /** Tercer intento de cierre: la oferta se pierde, también en el servidor. */
  const descartar = useCallback(async () => {
    trackUpsell("cerrado", { orderId, conPedidoPendiente: Boolean(pedido) });
    cerrarDelTodo();
    try {
      await dismissUpsell(orderId, accessToken);
    } catch {
      // Si falla la red, la oferta igual vence sola en el servidor. Al cliente no se
      // le muestra nada: ya decidió cerrar.
    }
  }, [orderId, accessToken, pedido, cerrarDelTodo]);

  /** X, Esc o clic fuera: cada intento avanza un paso hacia el cierre definitivo. */
  const intentarCerrar = useCallback(() => {
    if (creando) return;
    if (paso === "agregado") return cerrarDelTodo();
    if (paso === "oferta") {
      trackUpsell("cierre_paso_1", { orderId });
      return setPaso("confirmar1");
    }
    if (paso === "confirmar1") {
      trackUpsell("cierre_paso_2", { orderId });
      return setPaso("confirmar2");
    }
    void descartar();
  }, [creando, paso, orderId, cerrarDelTodo, descartar]);

  /** La oferta venció en el servidor con el modal abierto: aviso amable y se cierra. */
  const ofertaNoDisponible = useCallback(() => {
    toast(t("unavailable"));
    cerrarDelTodo();
  }, [t, cerrarDelTodo]);

  const comprar = async (lineas: UpsellOfferItem[]) => {
    if (lineas.length === 0) return;
    setCreando(true);
    try {
      const { order } = await createUpsellOrder(
        orderId,
        lineas.map((o) => ({ rule_id: o.rule_id, variant_id: tallaDe(o) })),
        accessToken
      );
      // El pedido existe pero sin pagar. El intent de Stripe se pide al elegir
      // tarjeta, para no crear uno si paga con PayPal.
      setPedido(order);
      setPaso("oferta");
    } catch (err: unknown) {
      if (estadoDelError(err) === 409) return ofertaNoDisponible();
      toast.error(mensajeDelError(err) ?? t("error"));
    } finally {
      setCreando(false);
    }
  };

  /** "Sí, agregarlo" desde un aviso: compra lo elegido, o la oferta que estaba viendo. */
  const agregarDesdeAviso = () => {
    if (pedido) return setPaso("oferta");
    const lineas = seleccionados.length > 0 ? seleccionados : actual ? [actual] : [];
    if (lineas.length !== seleccionados.length) setElegidos(new Set(lineas.map((o) => o.rule_id)));
    void comprar(lineas);
  };

  const pagarConTarjeta = async () => {
    if (!pedido) return;
    setCreando(true);
    try {
      const intent = await createStripeIntent(pedido.id, pedido.access_token ?? undefined);
      setStripeSecret(intent.client_secret);
    } catch (err: unknown) {
      toast.error(mensajeDelError(err) ?? t("error"));
    } finally {
      setCreando(false);
    }
  };

  const pagarConPaypal = async () => {
    if (!pedido) return;
    setCreando(true);
    try {
      const { approval_url } = await createPaypalOrder(pedido.id, pedido.access_token ?? undefined);
      if (!approval_url) throw new Error("sin approval_url");
      trackUpsell("agregado", { orderId, metodo: "paypal", pagoPendiente: true });
      // Al volver de PayPal la página de éxito es la del pedido nuevo, que no genera
      // más ofertas: no hace falta marcar nada acá.
      window.location.href = approval_url;
    } catch {
      toast.error(t("error"));
      setCreando(false);
    }
  };

  const alPagar = () => {
    setStripeSecret(null);
    trackUpsell("agregado", { orderId, metodo: "stripe", total });
    setPaso("agregado");
  };

  // "Agregado a tu pedido" se ve un momento y el modal se cierra solo.
  useEffect(() => {
    if (paso !== "agregado") return;
    const id = window.setTimeout(cerrarDelTodo, DURACION_AGREGADO_MS);
    return () => window.clearTimeout(id);
  }, [paso, cerrarDelTodo]);

  // Mientras el formulario de Stripe está encima, este modal se oculta: dos diálogos
  // compitiendo por el foco y por Esc confunden al lector de pantalla y al cliente.
  const visible = abierto && stripeSecret === null;

  // Scroll bloqueado y foco recordado mientras el modal está a la vista.
  useEffect(() => {
    if (!visible) return;
    focoPrevio.current = document.activeElement as HTMLElement | null;
    const overflowPrevio = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = overflowPrevio;
      focoPrevio.current?.focus?.();
    };
  }, [visible]);

  // Al cambiar de pantalla el foco va al primer control, para que el teclado y el
  // lector de pantalla sigan el flujo.
  useEffect(() => {
    if (!visible) return;
    const id = window.requestAnimationFrame(() => {
      const panel = panelRef.current;
      const primero = panel?.querySelector<HTMLElement>("[data-autofocus]") ??
        panel?.querySelector<HTMLElement>(SELECTOR_ENFOCABLES);
      primero?.focus();
    });
    return () => window.cancelAnimationFrame(id);
  }, [visible, paso, pedido]);

  // Esc = intento de cierre; Tab no sale del modal.
  useEffect(() => {
    if (!visible) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        intentarCerrar();
        return;
      }
      if (e.key !== "Tab" || !panelRef.current) return;
      const enfocables = Array.from(
        panelRef.current.querySelectorAll<HTMLElement>(SELECTOR_ENFOCABLES)
      ).filter((el) => el.offsetParent !== null);
      if (enfocables.length === 0) return;
      const primero = enfocables[0];
      const ultimo = enfocables[enfocables.length - 1];
      if (e.shiftKey && document.activeElement === primero) {
        e.preventDefault();
        ultimo.focus();
      } else if (!e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primero.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [visible, intentarCerrar]);

  const mover = (delta: number) =>
    setIndice((i) => (lista.length === 0 ? 0 : (i + delta + lista.length) % lista.length));

  if (!montado || lista.length === 0) return null;

  const transicion = reducirMovimiento
    ? { duration: 0 }
    : { type: "spring" as const, stiffness: 320, damping: 32 };

  return (
    <>
      {createPortal(
        <AnimatePresence>
          {visible && (
            <motion.div
              key="upsell"
              className="fixed inset-0 z-[100] flex items-end justify-center sm:items-center sm:p-6"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: reducirMovimiento ? 0 : 0.2 }}
            >
              <div
                className="absolute inset-0 bg-black/75 backdrop-blur-md"
                onClick={intentarCerrar}
                aria-hidden
              />

              <motion.div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={tituloId}
                className={cn(
                  "relative z-10 flex max-h-[92dvh] w-full flex-col overflow-hidden border border-white/10",
                  "bg-gradient-to-b from-[#1a1a1a] to-brand-carbon shadow-card-hover",
                  // Bottom sheet en móvil, tarjeta centrada en desktop.
                  "rounded-t-3xl sm:max-w-[440px] sm:rounded-3xl"
                )}
                initial={reducirMovimiento ? { opacity: 0 } : { opacity: 0, y: 48 }}
                animate={reducirMovimiento ? { opacity: 1 } : { opacity: 1, y: 0 }}
                exit={reducirMovimiento ? { opacity: 0 } : { opacity: 0, y: 48 }}
                transition={transicion}
              >
                {/* Filete rojo arriba: la firma visual del sitio. */}
                <span className="h-1 w-full shrink-0 bg-gradient-to-r from-brand-red via-brand-red/70 to-transparent" />
                {/* Agarradera del bottom sheet; en desktop no aplica. */}
                <span className="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-white/15 sm:hidden" aria-hidden />

                <button
                  type="button"
                  onClick={intentarCerrar}
                  aria-label={t("close")}
                  className="absolute right-3 top-3 z-20 rounded-full bg-black/40 p-2 text-brand-muted ring-1 ring-white/10 transition hover:bg-white/10 hover:text-brand-white"
                >
                  <X size={18} />
                </button>

                <div className="overflow-y-auto px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-4 sm:px-7 sm:pb-7">
                  {paso === "oferta" && actual && (
                    <PantallaOferta
                      tituloId={tituloId}
                      lista={lista}
                      indice={indice}
                      actual={actual}
                      orderNumber={orderNumber}
                      elegidos={elegidos}
                      tallaDe={tallaDe}
                      onTalla={(ruleId, variantId) => setTallas((p) => ({ ...p, [ruleId]: variantId }))}
                      onAlternar={alternar}
                      onMover={mover}
                      onIr={setIndice}
                      onToqueInicio={(x) => (toqueInicio.current = x)}
                      onToqueFin={(x) => {
                        if (toqueInicio.current === null) return;
                        const dx = x - toqueInicio.current;
                        toqueInicio.current = null;
                        if (Math.abs(dx) > 48) mover(dx < 0 ? 1 : -1);
                      }}
                      total={total}
                      ahorroTotal={ahorroTotal}
                      cantidad={seleccionados.length}
                      creando={creando}
                      pedido={pedido}
                      onComprar={() => comprar(seleccionados)}
                      onTarjeta={pagarConTarjeta}
                      onPaypal={pagarConPaypal}
                    />
                  )}

                  {paso === "confirmar1" && actual && (
                    <div className="pb-1 pt-6 text-center">
                      <span className="relative mx-auto block h-24 w-24 overflow-hidden rounded-2xl bg-white/[0.03] ring-1 ring-white/10">
                        <Image
                          src={getImageUrl(actual.product.primary_image)}
                          alt={actual.product.name}
                          fill
                          sizes="96px"
                          className="object-contain p-2"
                        />
                      </span>
                      <h2 id={tituloId} className="mt-5 text-xl font-extrabold text-brand-white">
                        {t("confirm1_title")}
                      </h2>
                      <div className="mt-3 flex justify-center">
                        <InsigniaAhorro grande ahorro={ahorroTotal > 0 ? ahorroTotal : ahorroDe(actual)} />
                      </div>
                      <p className="mt-2 text-sm text-brand-muted">{t("confirm1_body")}</p>
                      <div className="mt-3 flex justify-center">
                        <EnvioGratis discreta />
                      </div>
                      <div className="mt-6 flex flex-col gap-2.5">
                        <Button
                          type="button"
                          variant="primary"
                          className="w-full"
                          loading={creando}
                          onClick={agregarDesdeAviso}
                          data-autofocus
                        >
                          {t("confirm1_yes")}
                        </Button>
                        <button
                          type="button"
                          onClick={intentarCerrar}
                          className="rounded-xl py-3 text-sm font-medium text-brand-muted transition hover:text-brand-white"
                        >
                          {t("confirm1_no")}
                        </button>
                      </div>
                    </div>
                  )}

                  {paso === "confirmar2" && (
                    <div className="pb-1 pt-8 text-center">
                      <h2 id={tituloId} className="text-xl font-extrabold text-brand-white">
                        {t("confirm2_title", { number: orderNumber })}
                      </h2>
                      <p className="mt-2 text-sm text-brand-muted">{t("confirm2_body")}</p>
                      {actual && (
                        <div className="mt-4 flex justify-center">
                          <InsigniaAhorro grande ahorro={ahorroTotal > 0 ? ahorroTotal : ahorroDe(actual)} />
                        </div>
                      )}
                      <div className="mt-3 flex justify-center">
                        <EnvioGratis discreta />
                      </div>
                      <div className="mt-6 flex flex-col gap-2.5">
                        <Button
                          type="button"
                          variant="primary"
                          className="w-full"
                          loading={creando}
                          onClick={agregarDesdeAviso}
                          data-autofocus
                        >
                          {t("confirm2_add")}
                        </Button>
                        <button
                          type="button"
                          onClick={intentarCerrar}
                          className="rounded-xl py-3 text-sm font-medium text-brand-muted transition hover:text-brand-white"
                        >
                          {t("confirm2_close")}
                        </button>
                      </div>
                    </div>
                  )}

                  {paso === "agregado" && (
                    <div className="py-10 text-center" role="status">
                      <motion.span
                        initial={reducirMovimiento ? false : { scale: 0.6, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={transicion}
                        className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-cat-boots/15 ring-2 ring-cat-boots/40"
                      >
                        <Check size={32} className="text-cat-boots" strokeWidth={3} />
                      </motion.span>
                      <h2 id={tituloId} className="mt-5 text-xl font-extrabold text-brand-white">
                        {t("added", { number: orderNumber })}
                      </h2>
                      <p className="mt-2 text-sm text-brand-muted">
                        {t("ships_together", { number: orderNumber })}
                      </p>
                    </div>
                  )}
                </div>
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>,
        document.body
      )}

      <StripePaymentModal
        open={stripeSecret !== null}
        clientSecret={stripeSecret}
        accessToken={pedido?.access_token ?? undefined}
        returnUrl={typeof window !== "undefined" ? window.location.href : ""}
        // Cancelar la tarjeta vuelve al modal de la oferta, con el pedido intacto.
        onClose={() => setStripeSecret(null)}
        onPaid={alPagar}
      />
    </>
  );
}

interface PantallaOfertaProps {
  tituloId: string;
  lista: UpsellOfferItem[];
  indice: number;
  actual: UpsellOfferItem;
  orderNumber: string;
  elegidos: Set<number>;
  tallaDe: (o: UpsellOfferItem) => string;
  onTalla: (ruleId: number, variantId: string) => void;
  onAlternar: (o: UpsellOfferItem) => void;
  onMover: (delta: number) => void;
  onIr: (i: number) => void;
  onToqueInicio: (x: number) => void;
  onToqueFin: (x: number) => void;
  total: number;
  ahorroTotal: number;
  cantidad: number;
  creando: boolean;
  pedido: PedidoUpsell | null;
  onComprar: () => void;
  onTarjeta: () => void;
  onPaypal: () => void;
}

function PantallaOferta({
  tituloId,
  lista,
  indice,
  actual,
  orderNumber,
  elegidos,
  tallaDe,
  onTalla,
  onAlternar,
  onMover,
  onIr,
  onToqueInicio,
  onToqueFin,
  total,
  ahorroTotal,
  cantidad,
  creando,
  pedido,
  onComprar,
  onTarjeta,
  onPaypal,
}: PantallaOfertaProps) {
  const t = useTranslations("upsell");
  const varias = lista.length > 1;
  const marcado = elegidos.has(actual.rule_id);
  const variantes = actual.product.variants;
  const ahorro = ahorroDe(actual);

  return (
    <div>
      <p className="pr-10 text-[11px] font-bold uppercase tracking-[0.18em] text-brand-red">
        {t("context")}
      </p>
      <h2 id={tituloId} className="mt-1 pr-10 text-2xl font-extrabold uppercase tracking-tight text-brand-white">
        {t("title")}
      </h2>

      {/* Carrusel: imagen grande, flechas a los costados y deslizar en móvil. */}
      <div
        className="relative mt-4"
        onTouchStart={(e) => onToqueInicio(e.touches[0].clientX)}
        onTouchEnd={(e) => onToqueFin(e.changedTouches[0].clientX)}
        aria-roledescription={varias ? "carousel" : undefined}
      >
        <div className="relative aspect-[4/3] w-full overflow-hidden rounded-2xl bg-[radial-gradient(ellipse_at_center,rgba(255,255,255,0.07),transparent_70%)] ring-1 ring-white/10">
          <Image
            key={actual.rule_id}
            src={getImageUrl(actual.product.primary_image)}
            alt={actual.product.name}
            fill
            sizes="(min-width: 640px) 400px, 92vw"
            className="object-contain p-4"
            priority
          />
          <span className="absolute left-3 top-3 rounded-lg bg-brand-red px-2.5 py-1 text-sm font-black text-white shadow-red-glow">
            −{actual.discount_percent}%
          </span>
        </div>

        {varias && (
          <>
            <button
              type="button"
              onClick={() => onMover(-1)}
              aria-label={t("prev")}
              className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/60 p-2 text-brand-white ring-1 ring-white/15 backdrop-blur transition hover:bg-black/80"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              type="button"
              onClick={() => onMover(1)}
              aria-label={t("next")}
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/60 p-2 text-brand-white ring-1 ring-white/15 backdrop-blur transition hover:bg-black/80"
            >
              <ChevronRight size={18} />
            </button>
          </>
        )}
      </div>

      {varias && (
        <div className="mt-3 flex justify-center gap-2">
          {lista.map((o, i) => (
            <button
              key={o.rule_id}
              type="button"
              onClick={() => onIr(i)}
              aria-label={t("dot", { current: i + 1, total: lista.length })}
              aria-current={i === indice}
              className={cn(
                "h-2 rounded-full transition-all",
                i === indice ? "w-6 bg-brand-red" : "w-2 bg-white/25 hover:bg-white/40",
                elegidos.has(o.rule_id) && i !== indice && "bg-cat-boots/70"
              )}
            />
          ))}
        </div>
      )}

      <div className="mt-4" aria-live="polite">
        <h3 className="text-lg font-bold leading-snug text-brand-white">{actual.product.name}</h3>
        <BloquePrecio
          className="mt-2"
          original={parseFloat(actual.price)}
          ahorro={ahorro}
          porcentaje={actual.discount_percent}
          final={parseFloat(actual.discounted_price)}
        />
      </div>

      {variantes.length > 1 ? (
        <fieldset className="mt-4" disabled={Boolean(pedido)}>
          <legend className="text-[11px] font-semibold uppercase tracking-wider text-brand-muted">
            {t("size")}
          </legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {variantes.map((v) => {
              const activa = tallaDe(actual) === v.id;
              return (
                <button
                  key={v.id}
                  type="button"
                  onClick={() => onTalla(actual.rule_id, v.id)}
                  aria-pressed={activa}
                  className={cn(
                    "min-w-[3rem] rounded-xl border px-3 py-2 text-sm font-semibold transition disabled:opacity-40",
                    activa
                      ? "border-brand-red bg-brand-red/15 text-brand-white"
                      : "border-white/15 text-brand-muted hover:border-white/30 hover:text-brand-white"
                  )}
                >
                  {v.size ?? "—"}
                </button>
              );
            })}
          </div>
        </fieldset>
      ) : (
        variantes[0]?.size && (
          <p className="mt-3 text-xs text-brand-muted">
            {t("size")}: <span className="font-semibold text-brand-white">{variantes[0].size}</span>
          </p>
        )
      )}

      {!pedido && (
        <button
          type="button"
          onClick={() => onAlternar(actual)}
          aria-pressed={marcado}
          className={cn(
            "mt-4 flex w-full items-center justify-center gap-2 rounded-xl border py-3 text-sm font-bold transition",
            marcado
              ? "border-cat-boots/60 bg-cat-boots/10 text-cat-boots"
              : "border-white/15 text-brand-white hover:border-white/30"
          )}
        >
          {marcado && <Check size={16} strokeWidth={3} />}
          {marcado ? t("selected") : t("select_this")}
        </button>
      )}

      <div className="mt-4 rounded-xl border border-[#39FF14]/25 bg-[#39FF14]/[0.06] px-3.5 py-3 shadow-[0_0_14px_rgba(57,255,20,0.10)]">
        <EnvioGratis />
        {/* Gris más claro que brand-muted: sobre el fondo verdoso del bloque, el muted
            no llega a AA para texto de 12px. */}
        <p className="mt-1 text-xs text-[#a3a3a3]">{t("ships_together", { number: orderNumber })}</p>
      </div>

      <div className="mt-4 border-t border-white/10 pt-4">
        {!pedido ? (
          <>
            {/* Con una sola elegida el bloque de arriba ya lo dice, y el botón lleva el total. */}
            {cantidad > 1 && (
              <div className="mb-4" aria-live="polite">
                <p className="text-[11px] font-semibold uppercase tracking-wider text-[#a3a3a3]">
                  {t("total_label", { count: cantidad })}
                </p>
                <BloquePrecio
                  className="mt-1"
                  compacto
                  original={total + ahorroTotal}
                  ahorro={ahorroTotal}
                  final={total}
                />
              </div>
            )}
            <Button
              type="button"
              variant="primary"
              className="w-full py-4 text-base font-black tracking-wide"
              loading={creando}
              disabled={cantidad === 0}
              onClick={onComprar}
            >
              {cantidad === 0 ? t("select_one") : `${t("buy_now")} · ${formatPrice(total)}`}
            </Button>
          </>
        ) : (
          // El pedido ya existe: solo falta cobrarlo.
          <div className="space-y-3">
            <p className="text-sm text-brand-muted">
              {t("pay_prompt", { total: formatPrice(pedido.total) })}
            </p>
            {ENABLED_PAYMENT_METHODS.includes("paypal") && (
              <PaypalButton label={t("pay_with")} ariaLabel={t("pay_with")} loading={creando} onClick={onPaypal} />
            )}
            {ENABLED_PAYMENT_METHODS.includes("stripe") && (
              <Button type="button" variant="glass" className="w-full" loading={creando} onClick={onTarjeta}>
                {t("pay_card")}
              </Button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

/**
 * Verde neón del envío gratis. Sobre los fondos oscuros del modal, incluido el del
 * bloque teñido de verde, el contraste va de 11:1 a 14:1, muy por encima de AA. Pensado SOLO para fondos oscuros: sobre
 * blanco no se lee, así que no se usa fuera de este modal.
 */
const NEON = "#39FF14";

/**
 * Insignia "ENVÍO GRATIS". Es veraz: el producto no paga envío porque se despacha en
 * el mismo paquete que el pedido original. El brillo es una sombra fija y tenue, sin
 * ninguna animación, así que con prefers-reduced-motion se ve exactamente igual.
 */
function EnvioGratis({ discreta = false }: { discreta?: boolean }) {
  const t = useTranslations("upsell");

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 font-black uppercase tracking-wider",
        discreta
          ? "rounded-full border border-[#39FF14]/30 px-2.5 py-0.5 text-[11px]"
          : "text-sm"
      )}
      style={{
        color: NEON,
        textShadow: discreta ? undefined : "0 0 8px rgba(57,255,20,0.35)",
      }}
    >
      <Truck size={discreta ? 13 : 16} strokeWidth={2.5} aria-hidden />
      {t("free_shipping")}
    </span>
  );
}

/**
 * Original tachado → ahorro → precio a pagar, en ese orden de lectura. Todos los
 * montos salen de la oferta del servidor: original es el precio vigente del producto
 * (del que se calcula el descuento) y el ahorro es la resta exacta, nada estimado.
 *
 * El original va en gris claro (12:1 sobre el fondo) y no en el muted, con un tachado
 * grueso en rojo para que se lea como "antes" sin perder legibilidad. En 390px la
 * insignia baja sola debajo del original si no entra: flex-wrap y nada se parte.
 */
function BloquePrecio({
  original,
  ahorro,
  porcentaje,
  final,
  compacto = false,
  className,
}: {
  original: number;
  ahorro: number;
  porcentaje?: number;
  final: number;
  compacto?: boolean;
  className?: string;
}) {
  const t = useTranslations("upsell");

  return (
    <div className={className}>
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
        <span
          className={cn(
            "whitespace-nowrap font-bold tabular-nums text-[#d4d4d4] line-through decoration-brand-red decoration-[3px]",
            compacto ? "text-lg" : "text-xl sm:text-2xl"
          )}
        >
          <span className="sr-only">{t("was")} </span>
          {formatPrice(original)}
        </span>
        {ahorro > 0 && <InsigniaAhorro ahorro={ahorro} porcentaje={porcentaje} />}
      </div>

      <p
        className={cn(
          "font-semibold uppercase tracking-wider text-[#a3a3a3]",
          compacto ? "mt-1.5 text-[10px]" : "mt-2 text-[11px]"
        )}
      >
        {t("pay_only")}
      </p>
      <p
        className={cn(
          "whitespace-nowrap font-black leading-none tracking-tight tabular-nums text-white",
          compacto ? "mt-0.5 text-3xl" : "mt-1 text-4xl sm:text-5xl"
        )}
      >
        {formatPrice(final)}
      </p>
    </div>
  );
}

/**
 * "−25% · Ahorras $20.00". Blanco sobre el rojo de la marca: 4,7:1, pasa AA. El verde
 * neón queda reservado al envío gratis para que no compitan.
 */
function InsigniaAhorro({
  ahorro,
  porcentaje,
  grande = false,
}: {
  ahorro: number;
  porcentaje?: number;
  grande?: boolean;
}) {
  const t = useTranslations("upsell");

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-brand-red font-black text-white",
        grande ? "px-4 py-2 text-lg" : "px-2.5 py-1 text-sm"
      )}
    >
      {porcentaje !== undefined && (
        <>
          <span>−{porcentaje}%</span>
          <span aria-hidden className="opacity-60">·</span>
        </>
      )}
      <span>{t("you_save", { amount: formatPrice(ahorro) })}</span>
    </span>
  );
}

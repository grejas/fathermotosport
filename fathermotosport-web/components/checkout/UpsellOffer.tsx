"use client";

import {
  forwardRef,
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type RefObject,
} from "react";
import { createPortal } from "react-dom";
import Image from "next/image";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { AnimatePresence, motion, useIsPresent, useReducedMotion } from "framer-motion";
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

/**
 * Colores de texto medidos sobre el vidrio al 72% con una página BLANCA detrás, el peor
 * caso: el fondo de la tarjeta queda entre #424242 y #4d4d4d. Ahí este gris da 5:1 y
 * ROJO_TEXTO 5:1; el brand-muted, el gris anterior (#a3a3a3) y el rojo de la marca no
 * llegan a 4,5:1 para texto chico.
 */
const TEXTO_SECUNDARIO = "text-[#c2c2c2]";
const ROJO_TEXTO = "text-[#ffb3bc]";
/** Verde de "Ahorras" y "Elegido": el cat-boots no llega a 4,5:1 sobre el vidrio aclarado. */
const VERDE_TEXTO = "text-[#7af0cf]";

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
  // Oferta visible en el carrusel. La selección no depende de ella.
  const [indice, setIndice] = useState(0);
  // 1 hacia adelante, -1 hacia atrás: de qué lado entra la tarjeta nueva.
  const [direccion, setDireccion] = useState(0);
  // El anuncio "Oferta 2 de 3" solo tras navegar: al abrir ya se lee el título.
  const [anunciar, setAnunciar] = useState(false);
  // Nada preseleccionado: el cliente elige qué agregar.
  const [elegidos, setElegidos] = useState<Set<number>>(new Set());
  // Talla por oferta. Arranca en la primera disponible, pero no implica elegirla.
  const [tallas, setTallas] = useState<Record<number, string>>({});
  const [creando, setCreando] = useState(false);
  const [pedido, setPedido] = useState<PedidoUpsell | null>(null);
  const [stripeSecret, setStripeSecret] = useState<string | null>(null);

  const panelRef = useRef<HTMLDivElement>(null);
  const focoPrevio = useRef<HTMLElement | null>(null);
  const carruselRef = useRef<HTMLElement>(null);
  const diapositivaRef = useRef<HTMLDivElement>(null);

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
  const indiceVisible = Math.min(indice, Math.max(lista.length - 1, 0));
  // La que protagonizan los avisos de cierre: la primera elegida, o la que estaba viendo.
  const destacada = seleccionados[0] ?? lista[indiceVisible];

  /**
   * Pasa a otra oferta. Sin paso cíclico: en los extremos la flecha se deshabilita,
   * que junto al "1 / 3" deja claro dónde se está. Si el foco estaba en la tarjeta que
   * se va, pasa al carrusel: si no, caería en el body y Tab saldría del modal.
   */
  const irA = useCallback(
    (n: number) => {
      if (n < 0 || n >= lista.length || n === indiceVisible) return;
      if (diapositivaRef.current?.contains(document.activeElement)) {
        carruselRef.current?.focus({ preventScroll: true });
      }
      setDireccion(n > indiceVisible ? 1 : -1);
      setIndice(n);
      setAnunciar(true);
    },
    [lista.length, indiceVisible]
  );

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

  /**
   * Cada intento de cierre avanza un paso hacia el definitivo. La X y los botones
   * "No, gracias" / "Cerrar" lo hacen siempre; Esc y el clic fuera, solo desde la
   * oferta (ver intentarCerrarSuave): en los avisos hace falta una decisión explícita.
   */
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

  /** Esc y clic fuera: solo en el primer paso. */
  const intentarCerrarSuave = useCallback(() => {
    if (paso === "oferta") intentarCerrar();
  }, [paso, intentarCerrar]);

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
    const lineas = seleccionados.length > 0 ? seleccionados : destacada ? [destacada] : [];
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

  // Esc = intento de cierre; ← → cambian de oferta; Tab no sale del modal.
  useEffect(() => {
    if (!visible) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        intentarCerrarSuave();
        return;
      }
      if (
        (e.key === "ArrowLeft" || e.key === "ArrowRight") &&
        paso === "oferta" &&
        lista.length > 1 &&
        panelRef.current?.contains(document.activeElement)
      ) {
        e.preventDefault();
        irA(indiceVisible + (e.key === "ArrowRight" ? 1 : -1));
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
  }, [visible, intentarCerrarSuave, paso, lista.length, irA, indiceVisible]);

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
              className="fixed inset-0 z-[100] flex items-center justify-center p-4"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: reducirMovimiento ? 0 : 0.2 }}
            >
              {/* Overlay liviano: 25% + blur de 1,5px, para que la página se siga viendo.
                  Sin backdrop-filter (ni con prefijo -webkit-), 55%: oscuro pero no opaco. */}
              <div
                className="absolute inset-0 bg-black/55 supports-[(backdrop-filter:blur(0))_or_(-webkit-backdrop-filter:blur(0))]:bg-black/25 supports-[(backdrop-filter:blur(0))_or_(-webkit-backdrop-filter:blur(0))]:backdrop-blur-[1.5px]"
                onClick={intentarCerrarSuave}
                aria-hidden
              />

              <motion.div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={tituloId}
                className={cn(
                  // Centrado siempre: en móvil, el ancho de la pantalla menos 16px por lado.
                  "relative z-10 flex max-h-[85vh] w-[calc(100vw-32px)] max-w-[460px] flex-col overflow-hidden rounded-3xl",
                  "border border-white/[0.14] shadow-[0_24px_64px_rgba(0,0,0,0.55)]",
                  // Vidrio al 72% con blur de 10px. Sin backdrop-filter, al 94%: el texto
                  // nunca queda sobre la página nítida.
                  "bg-[#121212]/[0.94] supports-[(backdrop-filter:blur(0))_or_(-webkit-backdrop-filter:blur(0))]:bg-[#121212]/[0.72] supports-[(backdrop-filter:blur(0))_or_(-webkit-backdrop-filter:blur(0))]:backdrop-blur-[10px]"
                )}
                initial={reducirMovimiento ? { opacity: 0 } : { opacity: 0, y: 24, scale: 0.98 }}
                animate={reducirMovimiento ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
                exit={reducirMovimiento ? { opacity: 0 } : { opacity: 0, y: 24, scale: 0.98 }}
                transition={transicion}
              >
                {/* Filete rojo arriba: la firma visual del sitio. */}
                <span className="h-1 w-full shrink-0 bg-gradient-to-r from-brand-red via-brand-red/70 to-transparent" />

                <button
                  type="button"
                  onClick={intentarCerrar}
                  aria-label={t("close")}
                  className={cn(
                    "absolute right-3 top-3 z-20 rounded-full bg-white/[0.06] p-2 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white",
                    TEXTO_SECUNDARIO
                  )}
                >
                  <X size={18} />
                </button>

                {paso === "oferta" && (
                  <PantallaOferta
                    tituloId={tituloId}
                    lista={lista}
                    indice={indiceVisible}
                    direccion={direccion}
                    anunciar={anunciar}
                    onIr={irA}
                    reducirMovimiento={Boolean(reducirMovimiento)}
                    carruselRef={carruselRef}
                    diapositivaRef={diapositivaRef}
                    orderNumber={orderNumber}
                    elegidos={elegidos}
                    tallaDe={tallaDe}
                    onTalla={(ruleId, variantId) => setTallas((p) => ({ ...p, [ruleId]: variantId }))}
                    onAlternar={alternar}
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

                {paso !== "oferta" && (
                <div className="min-h-0 overflow-y-auto overscroll-contain px-5 pb-6 pt-4 sm:px-7">
                  {paso === "confirmar1" && destacada && (
                    <div className="pb-1 pt-6 text-center">
                      <span className="relative mx-auto block h-24 w-24 overflow-hidden rounded-2xl bg-white/[0.04] ring-1 ring-white/10">
                        <Image
                          src={getImageUrl(destacada.product.primary_image)}
                          alt={destacada.product.name}
                          fill
                          sizes="96px"
                          className="object-contain p-2"
                        />
                      </span>
                      <h2 id={tituloId} className="mt-5 text-xl font-extrabold text-white">
                        {t("confirm1_title")}
                      </h2>
                      <div className="mt-3 flex justify-center">
                        <InsigniaAhorro grande ahorro={ahorroTotal > 0 ? ahorroTotal : ahorroDe(destacada)} />
                      </div>
                      <p className={cn("mt-2 text-sm", TEXTO_SECUNDARIO)}>{t("confirm1_body")}</p>
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
                          className={cn("rounded-xl py-3 text-sm font-medium transition hover:text-white", TEXTO_SECUNDARIO)}
                        >
                          {t("confirm1_no")}
                        </button>
                      </div>
                    </div>
                  )}

                  {paso === "confirmar2" && (
                    <div className="pb-1 pt-8 text-center">
                      <h2 id={tituloId} className="text-xl font-extrabold text-white">
                        {t("confirm2_title", { number: orderNumber })}
                      </h2>
                      <p className={cn("mt-2 text-sm", TEXTO_SECUNDARIO)}>{t("confirm2_body")}</p>
                      {destacada && (
                        <div className="mt-4 flex justify-center">
                          <InsigniaAhorro grande ahorro={ahorroTotal > 0 ? ahorroTotal : ahorroDe(destacada)} />
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
                          className={cn("rounded-xl py-3 text-sm font-medium transition hover:text-white", TEXTO_SECUNDARIO)}
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
                      <h2 id={tituloId} className="mt-5 text-xl font-extrabold text-white">
                        {t("added", { number: orderNumber })}
                      </h2>
                      <p className={cn("mt-2 text-sm", TEXTO_SECUNDARIO)}>
                        {t("ships_together", { number: orderNumber })}
                      </p>
                    </div>
                  )}
                </div>
                )}
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
  direccion: number;
  anunciar: boolean;
  onIr: (indice: number) => void;
  reducirMovimiento: boolean;
  carruselRef: RefObject<HTMLElement>;
  diapositivaRef: RefObject<HTMLDivElement>;
  orderNumber: string;
  elegidos: Set<number>;
  tallaDe: (o: UpsellOfferItem) => string;
  onTalla: (ruleId: number, variantId: string) => void;
  onAlternar: (o: UpsellOfferItem) => void;
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
  direccion,
  anunciar,
  onIr,
  reducirMovimiento,
  carruselRef,
  diapositivaRef,
  orderNumber,
  elegidos,
  tallaDe,
  onTalla,
  onAlternar,
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
  const pistaId = useId();
  const toqueInicio = useRef<{ x: number; y: number } | null>(null);
  // Centro de la miniatura visible: las flechas van a esa altura.
  const [centroMiniatura, setCentroMiniatura] = useState<number | null>(null);
  const varias = lista.length > 1;
  const actual = lista[indice];
  const enPrimera = indice === 0;
  const enUltima = indice === lista.length - 1;
  const posicion = { current: indice + 1, total: lista.length };

  /** Deslizar en móvil: solo cuenta un gesto claramente horizontal. */
  const alSoltar = (x: number, y: number) => {
    const inicio = toqueInicio.current;
    toqueInicio.current = null;
    if (!inicio) return;
    const dx = x - inicio.x;
    if (Math.abs(dx) > 48 && Math.abs(dx) > Math.abs(y - inicio.y)) onIr(indice + (dx < 0 ? 1 : -1));
  };

  return (
    <>
      <header className="shrink-0 px-5 pb-3 pt-4 sm:px-6">
        <p className={cn("pr-10 text-[11px] font-bold uppercase tracking-[0.18em]", ROJO_TEXTO)}>
          {t("context")}
        </p>
        <h2 id={tituloId} className="mt-1 pr-10 text-xl font-extrabold uppercase tracking-tight text-white">
          {t("title")}
        </h2>
      </header>

      {/* Una oferta a la vez (hasta MAX_OFERTAS). Si en una pantalla muy baja no entra,
          scroll acá adentro: el resumen y el botón de abajo quedan siempre a la vista. */}
      <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-1 sm:px-6">
        <section
          ref={carruselRef}
          tabIndex={-1}
          role="region"
          aria-roledescription={varias ? t("carousel") : undefined}
          aria-label={t("carousel_label")}
          // touch-pan-y: el gesto horizontal es del carrusel. Sin esto el navegador lo
          // toma como "atrás" y deslizar hacia la oferta anterior sacaba de la página.
          className="touch-pan-y overscroll-x-contain rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-white/40"
          onTouchStart={(e) => (toqueInicio.current = { x: e.touches[0].clientX, y: e.touches[0].clientY })}
          onTouchEnd={(e) => alSoltar(e.changedTouches[0].clientX, e.changedTouches[0].clientY)}
        >
          {/* Alto fijo: cambiar de oferta no mueve nada de lo que está debajo. Las
              dos tarjetas se superponen durante la transición. Es el alto del caso más
              alto (nombre de 2 líneas + fila de tallas) más el padding y el borde: 326px
              con la miniatura de 112px; bajo 400px de ancho, 96px y 310px. */}
          <div className="relative h-[310px] overflow-hidden rounded-2xl min-[400px]:h-[326px]">
            <AnimatePresence initial={false} custom={direccion}>
              <Diapositiva
                key={actual.rule_id}
                ref={diapositivaRef}
                oferta={actual}
                etiqueta={varias ? t("dot", posicion) : undefined}
                direccion={direccion}
                reducirMovimiento={reducirMovimiento}
                marcado={elegidos.has(actual.rule_id)}
                tallaElegida={tallaDe(actual)}
                bloqueada={Boolean(pedido)}
                onTalla={(variantId) => onTalla(actual.rule_id, variantId)}
                onAlternar={() => onAlternar(actual)}
                onCentroMiniatura={setCentroMiniatura}
              />
            </AnimatePresence>

            {varias && (
              <>
                {/* aria-disabled y no disabled: un botón deshabilitado pierde el foco
                    justo cuando se llega al extremo con el teclado. */}
                <FlechaCarrusel lado="prev" etiqueta={t("prev")} inactiva={enPrimera} centro={centroMiniatura} reducirMovimiento={reducirMovimiento} onClick={() => onIr(indice - 1)} />
                <FlechaCarrusel lado="next" etiqueta={t("next")} inactiva={enUltima} centro={centroMiniatura} reducirMovimiento={reducirMovimiento} onClick={() => onIr(indice + 1)} />
              </>
            )}
          </div>

          {varias && (
            <div className="mt-2 flex items-center justify-center gap-2">
              <div className="flex items-center">
                {lista.map((o, i) => (
                  <button
                    key={o.rule_id}
                    type="button"
                    onClick={() => onIr(i)}
                    aria-label={t("dot", { current: i + 1, total: lista.length })}
                    aria-current={i === indice ? "true" : undefined}
                    // Área de toque de 24px; el punto visible es de 8px.
                    className="grid h-6 w-6 place-items-center rounded-full"
                  >
                    <span
                      className={cn(
                        "h-2 rounded-full transition-all motion-reduce:transition-none",
                        i === indice
                          ? "w-5 bg-brand-red"
                          : elegidos.has(o.rule_id)
                            ? "w-2 bg-cat-boots"
                            : "w-2 bg-white/35"
                      )}
                    />
                  </button>
                ))}
              </div>
              <span className={cn("text-xs font-semibold tabular-nums", TEXTO_SECUNDARIO)} aria-hidden>
                {t("position", posicion)}
              </span>
            </div>
          )}

          <p className="sr-only" aria-live="polite" aria-atomic="true">
            {anunciar && varias ? `${t("dot", posicion)}: ${actual.product.name}` : ""}
          </p>
        </section>
      </div>

      <footer className="shrink-0 border-t border-white/10 px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 sm:px-6">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <EnvioGratis discreta />
          <p className={cn("text-xs", TEXTO_SECUNDARIO)}>{t("ships_together", { number: orderNumber })}</p>
        </div>

        <div className="mt-3">
        {!pedido ? (
          <>
            {/* Con una sola elegida el bloque de arriba ya lo dice, y el botón lleva el total. */}
            <div aria-live="polite">
              {cantidad > 1 && (
                <p className="mb-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                  <span className={cn("font-semibold uppercase tracking-wider", TEXTO_SECUNDARIO)}>
                    {t("total_label", { count: cantidad })}
                  </span>
                  <span className="font-bold tabular-nums text-[#d4d4d4] line-through decoration-brand-red decoration-2">
                    <span className="sr-only">{t("was")} </span>
                    {formatPrice(total + ahorroTotal)}
                  </span>
                  <InsigniaAhorro ahorro={ahorroTotal} />
                </p>
              )}
            </div>
            <Button
              type="button"
              variant="primary"
              className="w-full py-3.5 text-base font-black tracking-wide"
              loading={creando}
              disabled={cantidad === 0}
              aria-describedby={cantidad === 0 ? pistaId : undefined}
              onClick={onComprar}
            >
              {cantidad === 0 ? t("select_one") : `${t("buy_now")} · ${formatPrice(total)}`}
            </Button>
            {cantidad === 0 && (
              <p id={pistaId} className={cn("mt-2 text-center text-xs", TEXTO_SECUNDARIO)}>
                {t("select_hint")}
              </p>
            )}
          </>
        ) : (
          // El pedido ya existe: solo falta cobrarlo.
          <div className="space-y-3">
            <p className={cn("text-sm", TEXTO_SECUNDARIO)}>
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
      </footer>
    </>
  );
}

interface DiapositivaProps {
  oferta: UpsellOfferItem;
  /** "Oferta 2 de 3"; sin valor cuando hay una sola y no hace falta. */
  etiqueta?: string;
  direccion: number;
  reducirMovimiento: boolean;
  marcado: boolean;
  tallaElegida: string;
  /** Con el pedido ya creado no se cambia la selección: solo falta pagar. */
  bloqueada: boolean;
  onTalla: (variantId: string) => void;
  onAlternar: () => void;
  /** Centro vertical de la miniatura, en px desde el borde de arriba de la tarjeta. */
  onCentroMiniatura: (y: number) => void;
}

/** Entra por el lado hacia el que se avanza y sale por el otro. */
const DESLIZAR = {
  entra: (d: number) => ({ x: d >= 0 ? "35%" : "-35%", opacity: 0 }),
  centro: { x: 0, opacity: 1 },
  sale: (d: number) => ({ x: d >= 0 ? "-35%" : "35%", opacity: 0 }),
};

/**
 * Una oferta del carrusel: miniatura grande con el porcentaje, nombre (2 líneas como
 * mucho), precio tachado, ahorro, "Pagas solo" y el botón para elegirla, como un bloque
 * compacto centrado en la tarjeta. La tarjeta tiene el alto del caso más alto (nombre
 * de 2 líneas y fila de tallas), así el modal no salta al pasar de una oferta a otra;
 * en las más bajas, lo que sobra queda repartido arriba y abajo.
 */
const Diapositiva = forwardRef<HTMLDivElement, DiapositivaProps>(function Diapositiva(
  { oferta, etiqueta, direccion, reducirMovimiento, marcado, tallaElegida, bloqueada, onTalla, onAlternar, onCentroMiniatura },
  ref
) {
  const t = useTranslations("upsell");
  const nombreId = useId();
  // La que sale queda unos instantes en pantalla: ni se lee ni se toca.
  const presente = useIsPresent();
  const variantes = oferta.product.variants;
  const miniaturaRef = useRef<HTMLSpanElement>(null);

  // Con el bloque centrado, la miniatura no está siempre a la misma altura: se informa
  // su centro para que las flechas la acompañen. Solo la oferta visible.
  useLayoutEffect(() => {
    const miniatura = miniaturaRef.current;
    if (!presente || !miniatura) return;
    const medir = () => onCentroMiniatura(miniatura.offsetTop + miniatura.offsetHeight / 2);
    medir();
    const observador = new ResizeObserver(medir);
    observador.observe(miniatura.parentElement ?? miniatura);
    return () => observador.disconnect();
  }, [presente, onCentroMiniatura]);

  return (
    <motion.div
      // Solo la que queda recibe el ref: el del carrusel apunta siempre a la visible.
      ref={presente ? ref : undefined}
      custom={direccion}
      variants={DESLIZAR}
      initial="entra"
      animate="centro"
      exit="sale"
      transition={reducirMovimiento ? { duration: 0 } : { duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
      role="group"
      aria-roledescription={etiqueta ? t("slide") : undefined}
      aria-label={etiqueta}
      aria-hidden={presente ? undefined : true}
      className={cn("absolute inset-0", !presente && "pointer-events-none")}
    >
      <div
        className={cn(
          // justify-center: lo que sobra va arriba y abajo, no entre los elementos.
          // [&>*]:shrink-0: ningún elemento se comprime (el nombre se recortaría).
          "flex h-full flex-col items-center justify-center rounded-2xl border px-3 py-3 text-center transition-colors [&>*]:shrink-0",
          marcado ? "border-cat-boots/60 bg-cat-boots/[0.07]" : "border-white/10 bg-white/[0.03]"
        )}
      >
        <span ref={miniaturaRef} className="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white/[0.05] ring-1 ring-white/10 min-[400px]:h-28 min-[400px]:w-28">
          <Image
            src={getImageUrl(oferta.product.primary_image)}
            alt=""
            fill
            sizes="(min-width: 400px) 112px, 96px"
            className="object-cover"
          />
          <span className="absolute left-0 top-0 rounded-br-lg bg-brand-red px-2 py-0.5 text-xs font-black leading-tight text-white">
            −{oferta.discount_percent}%
          </span>
        </span>

        <h3
          id={nombreId}
          title={oferta.product.name}
          className="mt-2 line-clamp-2 max-w-full text-sm font-bold leading-5 text-white"
        >
          {oferta.product.name}
        </h3>

        <p className="mt-1 flex items-center justify-center gap-2 text-xs">
          <span className="font-semibold tabular-nums text-[#d4d4d4] line-through decoration-brand-red decoration-2">
            <span className="sr-only">{t("was")} </span>
            {formatPrice(parseFloat(oferta.price))}
          </span>
          <span className={cn("font-semibold", VERDE_TEXTO)}>
            {t("you_save", { amount: formatPrice(ahorroDe(oferta)) })}
          </span>
        </p>
        <p className="mt-0.5 flex items-baseline justify-center gap-1.5">
          <span className={cn("text-[10px] font-semibold uppercase tracking-wider", TEXTO_SECUNDARIO)}>
            {t("pay_only")}
          </span>
          <span className="text-2xl font-black leading-tight tabular-nums text-white">
            {formatPrice(parseFloat(oferta.discounted_price))}
          </span>
        </p>

        {/* Fila de tallas, solo si hay que elegir. Hasta 7 entran en 375px; si hubiera
            más, se desliza de costado (sin cambiar de oferta) en vez de sumar renglones. */}
        {variantes.length > 1 ? (
          <div className="mt-2 flex h-8 w-full items-center justify-center">
            <fieldset
              className="max-w-full touch-pan-x overflow-x-auto overscroll-x-contain"
              disabled={bloqueada}
              // El gesto sobre la fila mueve las tallas, no el carrusel.
              onTouchStart={(e) => e.stopPropagation()}
              onTouchEnd={(e) => e.stopPropagation()}
            >
              <legend className="sr-only">{t("size")}</legend>
              <div className="flex w-max items-center gap-1">
                {variantes.length <= 4 && (
                  <span aria-hidden className={cn("mr-1 text-[11px] font-semibold uppercase tracking-wider", TEXTO_SECUNDARIO)}>
                    {t("size")}
                  </span>
                )}
                {variantes.map((v) => {
                  const activa = tallaElegida === v.id;
                  return (
                    <button
                      key={v.id}
                      type="button"
                      onClick={() => onTalla(v.id)}
                      aria-pressed={activa}
                      className={cn(
                        "h-8 min-w-[2.25rem] shrink-0 rounded-lg border px-2 text-xs font-semibold transition disabled:opacity-40",
                        activa
                          ? "border-brand-red bg-brand-red/15 text-white"
                          : cn("border-white/15 hover:border-white/30 hover:text-white", TEXTO_SECUNDARIO)
                      )}
                    >
                      {v.size ?? "—"}
                    </button>
                  );
                })}
              </div>
            </fieldset>
          </div>
        ) : (
          variantes[0]?.size && (
            <p className={cn("mt-2 text-[11px]", TEXTO_SECUNDARIO)}>
              {t("size")}: <span className="font-semibold text-white">{variantes[0].size}</span>
            </p>
          )
        )}

        {!bloqueada && (
          <button
            type="button"
            onClick={onAlternar}
            aria-pressed={marcado}
            aria-describedby={nombreId}
            className={cn(
              "mt-2.5 inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border text-sm font-bold transition",
              marcado
                ? cn("border-cat-boots/60 bg-cat-boots/15", VERDE_TEXTO)
                : "border-white/20 text-white hover:border-white/40"
            )}
          >
            {marcado && <Check size={16} strokeWidth={3} aria-hidden />}
            {marcado ? t("selected") : t("select_this")}
          </button>
        )}
      </div>
    </motion.div>
  );
});

/**
 * Flecha a un costado de la miniatura. Inactiva en el extremo, pero sigue enfocable.
 * Sigue el centro de la miniatura visible (que cambia de altura con el bloque
 * centrado), con la misma curva que el cambio de oferta.
 */
function FlechaCarrusel({
  lado,
  etiqueta,
  inactiva,
  centro,
  reducirMovimiento,
  onClick,
}: {
  lado: "prev" | "next";
  etiqueta: string;
  inactiva: boolean;
  centro: number | null;
  reducirMovimiento: boolean;
  onClick: () => void;
}) {
  const Icono = lado === "prev" ? ChevronLeft : ChevronRight;

  return (
    <button
      type="button"
      aria-label={etiqueta}
      aria-disabled={inactiva}
      onClick={inactiva ? undefined : onClick}
      style={{
        top: centro ?? undefined,
        transition: reducirMovimiento
          ? undefined
          : "top 280ms cubic-bezier(0.22, 1, 0.36, 1), background-color 150ms, opacity 150ms",
      }}
      className={cn(
        // Antes de la primera medición: el centro de la miniatura arriba de todo.
        "absolute top-[60px] min-[400px]:top-[68px] z-10 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-black/55 text-white ring-1 ring-white/15 transition",
        lado === "prev" ? "left-2" : "right-2",
        inactiva ? "cursor-default opacity-30" : "hover:bg-black/75"
      )}
    >
      <Icono size={18} aria-hidden />
    </button>
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

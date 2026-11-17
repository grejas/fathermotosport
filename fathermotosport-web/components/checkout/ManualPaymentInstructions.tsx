"use client";

import { useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { QRCodeSVG } from "qrcode.react";
import { AlertTriangle, Check, Clock, Copy, ExternalLink, Info, Landmark, MessageCircle, ShieldCheck } from "lucide-react";
import toast from "react-hot-toast";
import { useStoreSettings } from "@/components/layout/StoreSettingsProvider";
import { whatsappUrl } from "@/lib/api/storeSettings";
import { trackWhatsappClick } from "@/lib/analytics/events";
import { cn, formatPrice } from "@/lib/utils";
import type { ManualPaymentInstructions as Instrucciones, Order } from "@/lib/types";

/**
 * Datos para pagar por transferencia SEPA o cripto: monto exacto ya cotizado, a dónde
 * enviarlo (con botones de copiar y QR), el plazo y cómo mandar el comprobante.
 *
 * Todo sale del pedido (order.manual_payment), que el backend arma con los datos del
 * panel y la cotización fijada al crearlo: acá no se calcula ni se inventa nada.
 */
export function ManualPaymentInstructions({ order, pago }: { order: Order; pago: Instrucciones }) {
  const t = useTranslations("manual_payment");
  const { whatsappDigits } = useStoreSettings();
  const esSepa = pago.method === "sepa";
  const esBinance = pago.method === "binance_pay";
  const restante = useTiempoRestante(pago.expires_at);

  const qr = useMemo(() => contenidoQr(pago), [pago]);

  const mensaje = t("whatsapp_message", {
    number: order.order_number,
    amount: `${pago.amount} ${pago.currency}`,
    method: esSepa ? t("sepa_title") : esBinance ? "Binance Pay" : `${pago.currency} · ${pago.network}`,
  });
  const whatsappHref = whatsappUrl(whatsappDigits, mensaje);

  return (
    <div className="space-y-5">
      {/* Monto: lo primero que tiene que ver el cliente, y exacto. */}
      <div className="relative overflow-hidden rounded-2xl border border-brand-red/40 bg-gradient-to-br from-brand-red/15 via-brand-card to-brand-card p-5">
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-muted">
          {esSepa ? t("sepa_title") : esBinance ? t("binance_title") : t("crypto_title", { currency: pago.currency })}
        </p>
        <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-xs text-brand-muted">{t("exact_amount")}</p>
            <button
              type="button"
              onClick={() => copiar(pago.amount, t("copied"))}
              className="group mt-0.5 flex items-baseline gap-2 text-left"
              aria-label={t("copy_amount")}
            >
              <span className="font-mono text-3xl font-extrabold tracking-tight text-brand-white">{pago.amount}</span>
              <span className="text-lg font-bold text-brand-red">{pago.currency}</span>
              <Copy size={14} className="text-brand-muted transition group-hover:text-brand-white" />
            </button>
            {esBinance && <p className="mt-1 text-xs text-brand-white/80">{t("binance_alt_currencies")}</p>}
            <p className="mt-1 text-xs text-brand-muted">
              {t("equivalent", { total: formatPrice(order.total) })}
              {pago.currency !== "USDT" && pago.rate_usd && (
                <> · {t("rate", { currency: pago.currency, rate: formatearTasa(pago.rate_usd) })}</>
              )}
            </p>
          </div>
          {restante && (
            <div
              className={cn(
                "flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold",
                restante.urgente ? "border-brand-red/60 text-brand-red" : "border-white/15 text-brand-white/80"
              )}
            >
              <Clock size={13} />
              {restante.vencido ? t("expired") : t("time_left", { time: restante.texto })}
            </div>
          )}
        </div>
      </div>

      {/* Datos de destino */}
      <div className="rounded-2xl border border-white/10 bg-brand-card p-5">
        <div className={cn("grid gap-5", qr && "sm:grid-cols-[1fr_auto]")}>
          <div className="space-y-3">
            {esSepa ? (
              <>
                <Dato label={t("beneficiary")} valor={pago.beneficiary} />
                <Dato label="IBAN" valor={pago.iban} mono copiarComo={pago.iban?.replace(/\s/g, "")} />
                <Dato label="BIC / SWIFT" valor={pago.bic} mono />
                <Dato label={t("bank")} valor={pago.bank_name} />
                <Dato label={t("concept")} valor={pago.reference} mono destacado />
              </>
            ) : esBinance ? (
              <>
                <Dato label={t("binance_account")} valor={pago.merchant_name} destacado />
                {pago.pay_url && (
                  <a
                    href={pago.pay_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#F0B90B] px-4 py-3 text-sm font-bold text-[#181A20] shadow-lg shadow-[#F0B90B]/15 transition hover:brightness-105"
                  >
                    {t("binance_open")}
                    <ExternalLink size={15} />
                  </a>
                )}
                <p className="text-xs leading-relaxed text-brand-muted">{t("binance_how")}</p>
              </>
            ) : (
              <>
                <div>
                  <p className="text-[11px] font-semibold uppercase tracking-wider text-brand-muted">{t("network")}</p>
                  <p className="mt-1 inline-flex items-center gap-2 rounded-lg bg-brand-red px-3 py-1.5 text-sm font-bold text-white">
                    {pago.currency} · {pago.network}
                  </p>
                </div>
                <Dato label={t("address")} valor={pago.address} mono partir />
              </>
            )}
          </div>

          {qr && (
            <div className="flex flex-col items-center gap-2">
              <div className="rounded-xl bg-white p-3 shadow-lg shadow-black/30">
                <QRCodeSVG value={qr} size={152} level="M" marginSize={0} />
              </div>
              <p className="max-w-[180px] text-center text-[11px] leading-snug text-brand-muted">
                {esSepa ? t("qr_sepa") : esBinance ? t("qr_binance") : t("qr_crypto")}
              </p>
            </div>
          )}
        </div>

        {/* Advertencia propia de cada método. Binance Pay no tiene redes: solo se pide
            confirmar la cuenta antes de pagar. */}
        {esBinance ? (
          <div className="mt-5 flex gap-3 rounded-xl border border-[#F0B90B]/30 bg-[#F0B90B]/[0.07] p-3 text-xs leading-relaxed text-brand-white/85">
            <Info size={16} className="mt-0.5 shrink-0 text-[#F0B90B]" />
            <p>{t("binance_note", { name: pago.merchant_name ?? "FatherMotoSport" })}</p>
          </div>
        ) : (
          <div className="mt-5 flex gap-3 rounded-xl border border-amber-400/30 bg-amber-400/[0.07] p-3 text-xs leading-relaxed text-amber-100/90">
            <AlertTriangle size={16} className="mt-0.5 shrink-0 text-amber-300" />
            <p>
              {esSepa
                ? t("warning_sepa", { reference: pago.reference })
                : t("warning_crypto", { network: pago.network ?? "", currency: pago.currency })}
              {pago.method === "btc_bep20" && <strong className="mt-1 block">{t("warning_btc_bep20")}</strong>}
            </p>
          </div>
        )}
      </div>

      {/* Qué pasa después */}
      <ol className="space-y-3 rounded-2xl border border-white/10 bg-brand-card p-5">
        {[
          t(esBinance ? "binance_step_1" : "step_1", { amount: `${pago.amount} ${pago.currency}` }),
          t("step_2"),
          t("step_3"),
        ].map((paso, i) => (
          <li key={i} className="flex gap-3 text-sm text-brand-white/85">
            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-brand-white">
              {i + 1}
            </span>
            <span className="pt-0.5">{paso}</span>
          </li>
        ))}
      </ol>

      {whatsappHref && (
        <a
          href={whatsappHref}
          target="_blank"
          rel="noopener noreferrer"
          onClick={() => trackWhatsappClick("manual_payment")}
          className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#25D366]/20 transition hover:brightness-110"
        >
          <MessageCircle size={18} />
          {t("send_receipt")}
        </a>
      )}

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-brand-muted">
        {esSepa ? <Landmark size={13} /> : <ShieldCheck size={13} className="text-cat-boots" />}
        {t("auto_update")}
      </p>
    </div>
  );
}

function Dato({
  label,
  valor,
  mono,
  partir,
  destacado,
  copiarComo,
}: {
  label: string;
  valor: string | null | undefined;
  mono?: boolean;
  /** Direcciones largas: se cortan en cualquier carácter en vez de desbordar. */
  partir?: boolean;
  destacado?: boolean;
  /** Lo que se copia, si difiere de lo que se muestra (IBAN sin espacios). */
  copiarComo?: string;
}) {
  const t = useTranslations("manual_payment");
  const [copiado, setCopiado] = useState(false);
  if (!valor) return null;

  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-wider text-brand-muted">{label}</p>
      <div
        className={cn(
          "mt-1 flex items-start justify-between gap-3 rounded-lg border bg-black/20 px-3 py-2",
          destacado ? "border-brand-red/50" : "border-white/10"
        )}
      >
        <span
          className={cn(
            "text-sm text-brand-white",
            mono && "font-mono",
            partir ? "break-all" : "break-words",
            destacado && "font-bold"
          )}
        >
          {valor}
        </span>
        <button
          type="button"
          onClick={async () => {
            if (await copiar(copiarComo ?? valor, t("copied"))) {
              setCopiado(true);
              setTimeout(() => setCopiado(false), 1800);
            }
          }}
          className="shrink-0 rounded-md p-1 text-brand-muted transition hover:bg-white/10 hover:text-brand-white"
          aria-label={t("copy_label", { label })}
        >
          {copiado ? <Check size={15} className="text-cat-boots" /> : <Copy size={15} />}
        </button>
      </div>
    </div>
  );
}

async function copiar(texto: string, aviso: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(texto);
    toast.success(aviso);
    return true;
  } catch {
    return false;
  }
}

/**
 * QR para pagar sin tipear:
 *  - SEPA: código EPC ("girocode"), lo leen la mayoría de apps bancarias europeas y
 *    rellena titular, IBAN, monto y concepto.
 *  - BTC: URI BIP-21 con el monto.
 *  - USDT: solo la dirección; los exchanges no leen el monto de un QR de forma estándar.
 */
function contenidoQr(pago: Instrucciones): string | null {
  if (pago.method === "sepa") {
    if (!pago.iban || !pago.beneficiary) return null;
    return [
      "BCD",
      "002",
      "1",
      "SCT",
      pago.bic ?? "",
      pago.beneficiary.slice(0, 70),
      pago.iban.replace(/\s/g, ""),
      `EUR${pago.amount}`,
      "",
      "",
      pago.reference,
    ].join("\n");
  }

  // Binance Pay: el mismo enlace de cobro que muestra la app; al escanearlo se abre Binance.
  if (pago.method === "binance_pay") return pago.pay_url ?? null;

  if (!pago.address) return null;
  return pago.method === "btc" ? `bitcoin:${pago.address}?amount=${pago.amount}` : pago.address;
}

function formatearTasa(tasa: string): string {
  const n = Number(tasa);
  return n >= 1000
    ? n.toLocaleString("en-US", { maximumFractionDigits: 0 })
    : n.toLocaleString("en-US", { maximumFractionDigits: 4 });
}

/** Tiempo que le queda a la cotización; se refresca cada 30 s. */
function useTiempoRestante(expira: string | null) {
  const [ahora, setAhora] = useState(() => Date.now());

  useEffect(() => {
    const id = setInterval(() => setAhora(Date.now()), 30_000);
    return () => clearInterval(id);
  }, []);

  if (!expira) return null;
  const ms = new Date(expira).getTime() - ahora;
  if (Number.isNaN(ms)) return null;
  if (ms <= 0) return { vencido: true, urgente: true, texto: "" };

  const minutos = Math.floor(ms / 60_000);
  const horas = Math.floor(minutos / 60);
  const texto = horas >= 1 ? `${horas} h ${String(minutos % 60).padStart(2, "0")} min` : `${minutos} min`;

  return { vencido: false, urgente: ms < 60 * 60_000, texto };
}

"use client";

import type { ReactNode } from "react";
import { Bitcoin, CreditCard, Landmark, Smartphone, Wallet } from "lucide-react";
import { useTranslations } from "next-intl";
import { CRYPTO_PAYMENT_METHODS, isManualPaymentMethod } from "@/lib/data/paymentMethods";
import type { ManualPaymentMethod, PaymentMethod } from "@/lib/types";
import { cn } from "@/lib/utils";
import { CardLogos, MercadoPagoLogo, WalletLogos } from "@/components/ui/WalletLogos";

type Pasarela = "paypal" | "stripe_wallet" | "stripe" | "mercadopago";

const PASARELAS: { id: Pasarela; icon: typeof Wallet }[] = [
  { id: "paypal", icon: Wallet },
  // Billeteras primero: en el celular es el pago más rápido (un toque con Face ID).
  { id: "stripe_wallet", icon: Smartphone },
  { id: "stripe", icon: CreditCard },
  { id: "mercadopago", icon: Wallet },
];

/** Etiqueta corta de cada red cripto, como la muestran los exchanges al retirar. */
const REDES: Record<Exclude<ManualPaymentMethod, "sepa" | "us_bank" | "mercadopago_mx">, { moneda: string; red: string; monedas: string[] }> = {
  binance_pay: { moneda: "Binance Pay", red: "USDT · USDC · BTC", monedas: ["USDT", "USDC", "BTC"] },
  usdt_trc20: { moneda: "USDT", red: "TRC20", monedas: ["USDT"] },
  usdt_bep20: { moneda: "USDT", red: "BEP20", monedas: ["USDT"] },
  btc: { moneda: "BTC", red: "Bitcoin", monedas: ["BTC"] },
  btc_bep20: { moneda: "BTC", red: "BEP20 (BSC)", monedas: ["BTC"] },
};

interface Props {
  value: PaymentMethod;
  onChange: (m: PaymentMethod) => void;
  /** Pasarelas habilitadas; el resto se muestra en gris con "Próximamente". */
  enabled?: readonly PaymentMethod[];
  /** Transferencia / cripto configurados en el panel. Los que no están, no se muestran. */
  manual?: readonly ManualPaymentMethod[];
  comingSoonLabel?: string;
}

export function PaymentMethods({ value, onChange, enabled, manual = [], comingSoonLabel }: Props) {
  const t = useTranslations("checkout");
  const conSepa = manual.includes("sepa");
  const conUsBank = manual.includes("us_bank");
  const conMpMx = manual.includes("mercadopago_mx");
  const cripto = CRYPTO_PAYMENT_METHODS.filter((m) => manual.includes(m));
  const criptoActiva = cripto.includes(value as ManualPaymentMethod);

  return (
    <div className="space-y-3">
      {PASARELAS.map((m) => {
        const disponible = !enabled || enabled.includes(m.id);
        // Una pasarela apagada no se muestra: un botón gris en el checkout solo distrae.
        if (!disponible) return null;
        return (
          <Opcion
            key={m.id}
            icon={m.icon}
            label={t(`pm_${m.id}`)}
            desc={disponible ? t(`pm_${m.id}_desc`) : comingSoonLabel ?? t(`pm_${m.id}_desc`)}
            active={disponible && value === m.id}
            disabled={!disponible}
            // PayPal conserva su amarillo también cuando está elegido.
            iconClass={
              m.id === "paypal"
                ? "bg-[#FFC439] text-[#003087]"
                : m.id === "mercadopago"
                  ? "bg-[#00B1EA] text-white"
                  : undefined
            }
            // Billeteras y tarjeta van separadas, cada una con sus logos.
            extra={
              m.id === "stripe_wallet" ? (
                <WalletLogos variant="inline" className="mt-1.5" />
              ) : m.id === "stripe" ? (
                <CardLogos className="mt-1.5" />
              ) : m.id === "mercadopago" ? (
                <MercadoPagoLogo className="mt-1.5" />
              ) : undefined
            }
            onClick={() => onChange(m.id)}
          />
        );
      })}

      {/* Mercado Pago México por link: cobro en MXN al tipo de cambio del día. Para todos. */}
      {conMpMx && (
        <Opcion
          icon={Wallet}
          label={t("pm_mercadopago_mx")}
          desc={t("pm_mercadopago_mx_desc")}
          badge="MXN"
          active={value === "mercadopago_mx"}
          iconClass="bg-[#00B1EA] text-white"
          extra={<MercadoPagoLogo className="mt-1.5" />}
          onClick={() => onChange("mercadopago_mx")}
        />
      )}

      {conSepa && (
        <Opcion
          icon={Landmark}
          label={t("pm_sepa")}
          desc={t("pm_sepa_desc")}
          badge="EUR"
          active={value === "sepa"}
          onClick={() => onChange("sepa")}
        />
      )}

      {conUsBank && (
        <Opcion
          icon={Landmark}
          label={t("pm_us_bank")}
          desc={t("pm_us_bank_desc")}
          badge="USD"
          active={value === "us_bank"}
          onClick={() => onChange("us_bank")}
        />
      )}

      {cripto.length > 0 && (
        <div
          className={cn(
            "rounded-xl border transition",
            criptoActiva ? "border-brand-red bg-brand-red/10" : "border-white/10 hover:border-white/30"
          )}
        >
          <Opcion
            icon={Bitcoin}
            label={t("pm_crypto")}
            desc={t("pm_crypto_desc")}
            badge={[...new Set(cripto.flatMap((m) => REDES[m as keyof typeof REDES].monedas))].join(" · ")}
            active={criptoActiva}
            bare
            onClick={() => {
              if (!criptoActiva) onChange(cripto[0]);
            }}
          />

          {criptoActiva && (
            <div className="px-4 pb-4">
              <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-brand-muted">
                {t("pm_network")}
              </p>
              <div className="grid grid-cols-3 gap-2" role="radiogroup" aria-label={t("pm_network")}>
                {cripto.map((m) => {
                  const { moneda, red } = REDES[m as keyof typeof REDES];
                  const activa = value === m;
                  const binance = m === "binance_pay";
                  return (
                    <button
                      key={m}
                      type="button"
                      role="radio"
                      aria-checked={activa}
                      onClick={() => onChange(m)}
                      className={cn(
                        "rounded-lg border px-2 py-2 text-center transition",
                        activa
                          ? binance
                            ? "border-[#F0B90B] bg-[#F0B90B] text-[#181A20]"
                            : "border-brand-red bg-brand-red text-white"
                          : "border-white/15 bg-white/5 text-brand-white hover:border-white/40"
                      )}
                    >
                      <span className="block text-sm font-bold">{moneda}</span>
                      <span
                        className={cn(
                          "block text-[11px]",
                          activa ? (binance ? "text-[#181A20]/75" : "text-white/85") : "text-brand-muted"
                        )}
                      >
                        {red}
                      </span>
                    </button>
                  );
                })}
              </div>
              <p className="mt-2 text-[11px] leading-relaxed text-brand-muted">
                {value === "binance_pay" ? t("pm_binance_hint") : t("pm_crypto_network_hint")}
              </p>
            </div>
          )}
        </div>
      )}

      {isManualPaymentMethod(value) && (
        <p className="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs leading-relaxed text-brand-muted">
          {value === "mercadopago_mx"
            ? t("pm_mercadopago_mx_note")
            : value === "sepa"
            ? t("pm_sepa_note")
            : value === "us_bank"
              ? t("pm_us_bank_note")
              : value === "binance_pay"
              ? t("pm_binance_note")
              : t("pm_crypto_note")}
        </p>
      )}
    </div>
  );
}

function Opcion({
  icon: Icon,
  label,
  desc,
  badge,
  active,
  disabled,
  iconClass,
  bare,
  extra,
  onClick,
}: {
  icon: typeof Wallet;
  label: string;
  desc: string;
  badge?: string;
  active: boolean;
  disabled?: boolean;
  iconClass?: string;
  /** Sin borde propio: va dentro de otro contenedor (cripto). */
  bare?: boolean;
  /** Contenido debajo de la descripción (p. ej. logos de billeteras). */
  extra?: ReactNode;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "flex w-full items-center gap-3 p-4 text-left transition",
        !bare && "rounded-xl border",
        !bare && (active ? "border-brand-red bg-brand-red/10" : "border-white/10 hover:border-white/30"),
        disabled && "cursor-not-allowed opacity-40 hover:border-white/10"
      )}
    >
      <span
        className={cn(
          "flex h-10 w-10 shrink-0 items-center justify-center rounded-lg",
          iconClass ?? (active ? "bg-brand-red text-white" : "bg-white/5 text-brand-muted")
        )}
      >
        <Icon size={18} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-2 text-sm font-semibold text-brand-white">
          {label}
          {badge && (
            <span className="rounded-md border border-white/15 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-brand-muted">
              {badge}
            </span>
          )}
        </p>
        <p className="text-xs text-brand-muted">{desc}</p>
        {extra}
      </div>
      {!disabled && (
        <span
          className={cn("h-4 w-4 shrink-0 rounded-full border-2", active ? "border-brand-red bg-brand-red" : "border-white/30")}
        />
      )}
    </button>
  );
}

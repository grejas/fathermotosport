"use client";

import { useEffect, useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { Check, Copy, Gift, Mail, MessageCircle, Truck } from "lucide-react";
import toast from "react-hot-toast";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { claimPopupCoupon, type PopupCoupon } from "@/lib/api/popup";
import { trackLead } from "@/lib/analytics/events";
import { SHIPPING_COUNTRIES } from "@/lib/data/shippingCountries";
import { captureUtm, readUtm } from "@/lib/utm";
import { useCartStore } from "@/store/cartStore";

/** Cuánto espera antes de abrirse (pauta: ~8 s, para no interrumpir de entrada). */
const DELAY_MS = 8000;
/** Si lo cierra sin dejar el email, no vuelve a salir en estos días. */
const SNOOZE_DAYS = 7;
const KEY = "fms-popup";

type Guardado = { state: "closed"; at: number } | { state: "claimed"; code: string; percent: number; expires_at: string | null };

function leer(): Guardado | null {
  try {
    return JSON.parse(localStorage.getItem(KEY) || "null") as Guardado | null;
  } catch {
    return null;
  }
}

function guardar(v: Guardado): void {
  try {
    localStorage.setItem(KEY, JSON.stringify(v));
  } catch {
    // Sin localStorage: el popup podría volver a salir; no es grave.
  }
}

/** No se muestra donde estorba la compra o el acceso. */
const RUTAS_EXCLUIDAS = /\/(checkout|auth|orders|account|profile|unsubscribe)(\/|$)/;

/**
 * Popup de captación: el visitante deja su email (y WhatsApp si quiere) y descubre un
 * cupón de 10, 15, 20 o 25 % para su primera compra, de un solo uso y con vencimiento.
 * El porcentaje lo decide el servidor, nunca el navegador.
 *
 * Sale una vez, a los 8 s, fuera del checkout. Cerrado sin dejar el email, vuelve a
 * ofrecerse a los 7 días. Con el cupón ya entregado no vuelve a salir.
 */
export function PromoPopup() {
  const t = useTranslations("promo_popup");
  const tCountries = useTranslations("countries");
  const locale = useLocale();
  const pathname = usePathname() ?? "";
  const setPendingCoupon = useCartStore((s) => s.setPendingCoupon);

  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [whatsapp, setWhatsapp] = useState("");
  const [country, setCountry] = useState("");
  const [consent, setConsent] = useState(false);
  const [trampa, setTrampa] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const [premio, setPremio] = useState<PopupCoupon | null>(null);
  const [copiado, setCopiado] = useState(false);

  const excluida = RUTAS_EXCLUIDAS.test(pathname);

  useEffect(() => {
    captureUtm();
  }, []);

  useEffect(() => {
    if (excluida || open || premio) return;
    const g = leer();
    if (g?.state === "claimed") return;
    if (g?.state === "closed" && Date.now() - g.at < SNOOZE_DAYS * 86_400_000) return;

    const id = window.setTimeout(() => setOpen(true), DELAY_MS);
    return () => window.clearTimeout(id);
  }, [excluida, open, premio]);

  // Si navega al checkout con el popup abierto, se cierra sin marcarlo como rechazado.
  useEffect(() => {
    if (excluida && open) setOpen(false);
  }, [excluida, open]);

  const paises = useMemo(
    () =>
      SHIPPING_COUNTRIES.map((c) => ({ code: c.code, name: tCountries.has(c.code) ? tCountries(c.code) : c.name })).sort(
        (a, b) => a.name.localeCompare(b.name, locale)
      ),
    [tCountries, locale]
  );

  const cerrar = () => {
    setOpen(false);
    if (!premio) guardar({ state: "closed", at: Date.now() });
  };

  const enviar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    const mail = email.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mail)) return setError(t("error_email"));
    const wa = whatsapp.trim();
    if (wa && !/^\+[0-9 ()-]{7,20}$/.test(wa)) return setError(t("error_whatsapp"));
    if (!consent) return setError(t("error_consent"));

    setSending(true);
    const res = await claimPopupCoupon({
      email: mail,
      whatsapp: wa || undefined,
      country: country || undefined,
      locale,
      consent: true,
      source: { ...readUtm(), page: pathname },
      website: trampa || undefined,
    });
    setSending(false);

    if (!res.ok) {
      setError(t(`error_${res.reason}`));
      if (res.reason === "already_used") guardar({ state: "claimed", code: "", percent: 0, expires_at: null });
      return;
    }

    setPremio(res.coupon);
    setPendingCoupon(res.coupon.code);
    guardar({ state: "claimed", code: res.coupon.code, percent: res.coupon.percent, expires_at: res.coupon.expires_at });
    if (res.coupon.status === "new") trackLead(res.coupon.percent, mail, wa || undefined);
  };

  const copiar = async () => {
    if (!premio) return;
    try {
      await navigator.clipboard.writeText(premio.code);
      setCopiado(true);
      window.setTimeout(() => setCopiado(false), 2000);
    } catch {
      toast(premio.code);
    }
  };

  const vence = premio?.expires_at
    ? new Date(premio.expires_at).toLocaleDateString(locale, { day: "numeric", month: "long" })
    : null;

  return (
    <Modal open={open} onClose={cerrar}>
      {!premio ? (
        <form onSubmit={enviar} noValidate className="space-y-4">
          <div className="text-center">
            <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-gold/40 bg-brand-gold/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-gold">
              <Gift size={14} /> {t("badge")}
            </span>
            <h2 className="mt-3 text-3xl font-black uppercase leading-none tracking-tight text-brand-white sm:text-4xl">
              {t("title_before")} <span className="text-brand-red">{t("title_highlight")}</span>
            </h2>
            <p className="mt-2 text-sm text-brand-muted">{t("subtitle")}</p>
          </div>

          <div className="grid grid-cols-4 gap-2" aria-hidden>
            {[10, 15, 20, 25].map((p) => (
              <div
                key={p}
                className="rounded-xl border border-white/10 bg-brand-card py-2 text-center text-lg font-black text-brand-gold"
              >
                {p}%
              </div>
            ))}
          </div>

          <Input
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            placeholder={t("email_placeholder")}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            leftIcon={<Mail size={16} />}
            aria-label={t("email_placeholder")}
          />
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1.2fr]">
            <select
              value={country}
              onChange={(e) => setCountry(e.target.value)}
              aria-label={t("country")}
              className="w-full rounded-xl border border-white/10 bg-brand-card px-3 py-3 text-sm text-brand-white focus:border-brand-red focus:outline-none"
            >
              <option value="">{t("country")}</option>
              {paises.map((p) => (
                <option key={p.code} value={p.code}>
                  {p.name}
                </option>
              ))}
            </select>
            <Input
              name="whatsapp"
              type="tel"
              autoComplete="tel"
              inputMode="tel"
              placeholder={t("whatsapp_placeholder")}
              value={whatsapp}
              onChange={(e) => setWhatsapp(e.target.value)}
              leftIcon={<MessageCircle size={16} />}
              aria-label={t("whatsapp_placeholder")}
            />
          </div>

          {/* Trampa para bots: invisible para personas. */}
          <input
            type="text"
            name="website"
            tabIndex={-1}
            autoComplete="off"
            value={trampa}
            onChange={(e) => setTrampa(e.target.value)}
            className="absolute -left-[9999px] h-0 w-0 opacity-0"
            aria-hidden
          />

          <label className="flex cursor-pointer items-start gap-2.5 text-xs leading-relaxed text-brand-muted">
            <input
              type="checkbox"
              checked={consent}
              onChange={(e) => setConsent(e.target.checked)}
              className="mt-0.5 h-4 w-4 shrink-0 accent-[#E8001D]"
            />
            <span>{t("consent")}</span>
          </label>

          {error && (
            <p role="alert" className="rounded-lg bg-brand-red/10 px-3 py-2 text-sm text-brand-red">
              {error}
            </p>
          )}

          <Button type="submit" className="w-full" size="lg" loading={sending}>
            {t("cta")}
          </Button>

          <p className="flex items-center justify-center gap-1.5 text-center text-xs text-brand-muted">
            <Truck size={14} /> {t("footer")}
          </p>
        </form>
      ) : (
        <div className="space-y-5 text-center">
          <p className="text-sm font-semibold uppercase tracking-wider text-brand-gold">
            {premio.status === "existing" ? t("existing_title") : t("won_title")}
          </p>
          <p className="text-6xl font-black leading-none text-brand-red sm:text-7xl">
            {premio.percent}% <span className="text-brand-white">OFF</span>
          </p>

          <button
            type="button"
            onClick={copiar}
            className="mx-auto flex w-full max-w-xs items-center justify-between rounded-xl border-2 border-dashed border-brand-gold/60 bg-brand-card px-4 py-3 font-mono text-xl font-bold tracking-widest text-brand-white transition hover:border-brand-gold"
            aria-label={t("copy")}
          >
            {premio.code}
            {copiado ? <Check size={18} className="text-cat-boots" /> : <Copy size={18} className="text-brand-gold" />}
          </button>

          <p className="text-xs text-brand-muted">
            {vence ? t("terms", { date: vence }) : t("terms_no_date")}
            {premio.minimum_amount > 0 ? ` ${t("minimum", { amount: premio.minimum_amount })}` : ""}
          </p>

          <Button type="button" className="w-full" size="lg" onClick={() => setOpen(false)}>
            {t("apply")}
          </Button>
          <p className="text-xs text-brand-muted">{t("applied_note")}</p>
        </div>
      )}
    </Modal>
  );
}

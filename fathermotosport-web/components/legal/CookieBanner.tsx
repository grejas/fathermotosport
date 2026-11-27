"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Cookie, X } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { cn } from "@/lib/utils";
import { openConsentSettings, readConsent, saveConsent, useConsentOpenRequest } from "@/lib/analytics/consent";

/** Cookies que dejan GA4, Meta y TikTok: se borran si el visitante retira el permiso. */
const TRACKING_COOKIES = /^(_ga|_gid|_gat|_fbp|_fbc|_ttp|_tt_|ttcsid|tt_)/;

function clearTrackingCookies(): void {
  const host = window.location.hostname;
  const domains = ["", host, `.${host}`, `.${host.replace(/^www\./, "")}`];
  for (const part of document.cookie.split(";")) {
    const name = part.split("=")[0]?.trim();
    if (!name || !TRACKING_COOKIES.test(name)) continue;
    for (const d of domains) {
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${d ? `; domain=${d}` : ""}`;
    }
  }
}

/**
 * Banner de cookies. Sale en la primera visita y cuando se abre desde "Preferencias de
 * cookies" en el pie. "Rechazar" y "Aceptar todas" tienen el mismo tamaño y están a la
 * misma distancia (exigencia de las autoridades europeas); hasta elegir no se carga ningún píxel.
 */
export function CookieBanner() {
  const t = useTranslations("cookies");
  const [open, setOpen] = useState(false);
  const [settings, setSettings] = useState(false);
  const [analytics, setAnalytics] = useState(false);
  const [marketing, setMarketing] = useState(false);

  useEffect(() => {
    if (!readConsent()) setOpen(true);
  }, []);

  const reopen = useCallback(() => {
    const c = readConsent();
    setAnalytics(c?.analytics ?? false);
    setMarketing(c?.marketing ?? false);
    setSettings(true);
    setOpen(true);
  }, []);
  useConsentOpenRequest(reopen);

  const decide = (choice: { analytics: boolean; marketing: boolean }) => {
    const before = readConsent();
    saveConsent(choice);
    setOpen(false);
    setSettings(false);
    const revoked =
      (before?.analytics && !choice.analytics) || (before?.marketing && !choice.marketing);
    if (revoked) {
      // Los scripts ya cargados no se pueden descargar: se borran sus cookies y se recarga.
      clearTrackingCookies();
      window.location.reload();
    }
  };

  if (!open) return null;

  return (
    <div
      role="dialog"
      aria-modal="false"
      aria-labelledby="cookie-banner-title"
      className="fixed inset-x-0 bottom-0 z-[80] p-3 sm:p-4"
    >
      <div className="mx-auto max-w-3xl rounded-2xl border border-white/10 bg-brand-card/95 p-5 shadow-2xl shadow-black/60 backdrop-blur-md sm:p-6">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-red/10 text-brand-red">
            <Cookie size={18} aria-hidden />
          </span>
          <div className="min-w-0 flex-1">
            <h2 id="cookie-banner-title" className="text-base font-bold text-brand-white">
              {t("title")}
            </h2>
            <p className="mt-1 text-sm leading-relaxed text-brand-muted">
              {t("text")}{" "}
              <Link href="/privacy" className="text-brand-white underline underline-offset-2 hover:text-brand-red">
                {t("more")}
              </Link>
            </p>
          </div>
          {settings && (
            <button
              type="button"
              onClick={() => (readConsent() ? setOpen(false) : setSettings(false))}
              aria-label={t("close")}
              className="rounded-full p-1 text-brand-muted transition hover:text-brand-white"
            >
              <X size={18} />
            </button>
          )}
        </div>

        {settings && (
          <div className="mt-4 space-y-2">
            <Row title={t("necessary_title")} text={t("necessary_text")} checked disabled label={t("always_on")} />
            <Row
              title={t("analytics_title")}
              text={t("analytics_text")}
              checked={analytics}
              onChange={setAnalytics}
            />
            <Row
              title={t("marketing_title")}
              text={t("marketing_text")}
              checked={marketing}
              onChange={setMarketing}
            />
          </div>
        )}

        <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-3">
          <button
            type="button"
            onClick={() => decide({ analytics: false, marketing: false })}
            className="h-11 rounded-xl border border-white/20 bg-white/5 px-4 text-sm font-semibold text-brand-white transition hover:border-white/40 hover:bg-white/10"
          >
            {t("reject")}
          </button>
          {settings ? (
            <button
              type="button"
              onClick={() => decide({ analytics, marketing })}
              className="h-11 rounded-xl border border-white/20 bg-white/5 px-4 text-sm font-semibold text-brand-white transition hover:border-white/40 hover:bg-white/10"
            >
              {t("save")}
            </button>
          ) : (
            <button
              type="button"
              onClick={reopen}
              className="h-11 rounded-xl border border-white/20 bg-white/5 px-4 text-sm font-semibold text-brand-white transition hover:border-white/40 hover:bg-white/10"
            >
              {t("settings")}
            </button>
          )}
          <button
            type="button"
            onClick={() => decide({ analytics: true, marketing: true })}
            className="h-11 rounded-xl bg-brand-red px-4 text-sm font-semibold text-white transition hover:brightness-110"
          >
            {t("accept")}
          </button>
        </div>
      </div>
    </div>
  );
}

function Row({
  title,
  text,
  checked,
  onChange,
  disabled,
  label,
}: {
  title: string;
  text: string;
  checked: boolean;
  onChange?: (v: boolean) => void;
  disabled?: boolean;
  label?: string;
}) {
  return (
    <label
      className={cn(
        "flex items-start gap-3 rounded-xl border border-white/10 bg-black/20 p-3",
        disabled ? "cursor-default" : "cursor-pointer hover:border-white/20"
      )}
    >
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-semibold text-brand-white">{title}</span>
        <span className="mt-0.5 block text-xs leading-relaxed text-brand-muted">{text}</span>
      </span>
      {label ? (
        <span className="shrink-0 pt-0.5 text-xs font-semibold text-cat-boots">{label}</span>
      ) : (
        <span className="relative mt-0.5 inline-flex shrink-0">
          <input
            type="checkbox"
            className="peer sr-only"
            checked={checked}
            disabled={disabled}
            onChange={(e) => onChange?.(e.target.checked)}
          />
          <span className="h-6 w-11 rounded-full bg-white/15 transition peer-checked:bg-brand-red peer-focus-visible:ring-2 peer-focus-visible:ring-brand-red/60" />
          <span className="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5" />
        </span>
      )}
    </label>
  );
}

/** Enlace "Preferencias de cookies" del pie: reabre el banner. */
export function CookieSettingsLink({ className }: { className?: string }) {
  const t = useTranslations("cookies");
  return (
    <button
      type="button"
      onClick={openConsentSettings}
      className={className}
    >
      {t("footer_link")}
    </button>
  );
}

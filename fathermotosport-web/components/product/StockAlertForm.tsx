"use client";

import { useState, type FormEvent } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Bell, CheckCircle2, Mail, MessageCircle } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { requestStockAlert } from "@/lib/api/stockAlert";

/**
 * «Avísame cuando esté disponible»: se muestra en lugar de «Agregar al carrito»
 * cuando el producto está agotado en todas sus tallas. Guarda email, WhatsApp
 * (opcional) y la talla que busca; el equipo los ve en el panel (Avisos de stock).
 */
export function StockAlertForm({
  slug,
  sizes,
  sizeLabel,
}: {
  slug: string;
  sizes: string[];
  sizeLabel: string;
}) {
  const t = useTranslations("stock_alert");
  const locale = useLocale();
  const [email, setEmail] = useState("");
  const [whatsapp, setWhatsapp] = useState("");
  const [size, setSize] = useState("");
  const [trampa, setTrampa] = useState("");
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setSending(true);
    const res = await requestStockAlert(slug, {
      email: email.trim(),
      whatsapp: whatsapp.trim() || undefined,
      size: size || undefined,
      locale,
      website: trampa || undefined,
    });
    setSending(false);
    if (res.ok) {
      setDone(true);
      return;
    }
    setError(t(`error_${res.reason}`));
  };

  if (done) {
    return (
      <div className="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
        <CheckCircle2 size={20} className="mt-0.5 shrink-0 text-emerald-400" />
        <p className="text-sm text-brand-white">{t("success")}</p>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="relative space-y-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
      <div className="flex items-start gap-2">
        <Bell size={18} className="mt-0.5 shrink-0 text-brand-red" />
        <div>
          <p className="text-sm font-semibold text-brand-white">{t("title")}</p>
          <p className="text-xs text-brand-muted">{t("subtitle")}</p>
        </div>
      </div>

      {sizes.length > 0 && (
        <select
          value={size}
          onChange={(e) => setSize(e.target.value)}
          aria-label={sizeLabel}
          className="w-full rounded-xl border border-white/10 bg-brand-card px-3 py-3 text-sm text-brand-white focus:border-brand-red focus:outline-none"
        >
          <option value="">
            {sizeLabel}: {t("any_size")}
          </option>
          {sizes.map((s) => (
            <option key={s} value={s}>
              {sizeLabel}: {s}
            </option>
          ))}
        </select>
      )}

      <Input
        name="email"
        type="email"
        required
        autoComplete="email"
        placeholder={t("email_placeholder")}
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        leftIcon={<Mail size={16} />}
        aria-label={t("email_placeholder")}
      />
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

      {error && <p className="text-xs text-brand-red">{error}</p>}

      <Button type="submit" variant="primary" className="w-full" disabled={sending} icon={<Bell size={18} />}>
        {sending ? t("sending") : t("button")}
      </Button>
    </form>
  );
}

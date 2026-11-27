"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { CheckCircle2, Loader2 } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { sendWithdrawal, type WithdrawalResult } from "@/lib/api/withdrawal";
import { cn } from "@/lib/utils";

/**
 * Función de desistimiento (art. 11a Directiva 2011/83, vigente desde 19/06/2026):
 * el cliente identifica el pedido, confirma con un botón inequívoco y recibe el acuse
 * con fecha y hora por correo. Paso 1 = "Desistir del contrato", paso 2 = "Confirmar".
 */
export function WithdrawalForm() {
  const t = useTranslations("withdrawal");
  const locale = useLocale();
  const [step, setStep] = useState<"start" | "form">("start");
  const [form, setForm] = useState({ order_number: "", name: "", email: "", items: "", reason: "", website: "" });
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<WithdrawalResult | null>(null);

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (!form.order_number.trim() || !form.name.trim() || !/^\S+@\S+\.\S+$/.test(form.email.trim())) {
      setError(t("error_required"));
      return;
    }
    setSending(true);
    try {
      const r = await sendWithdrawal({
        order_number: form.order_number.trim(),
        name: form.name.trim(),
        email: form.email.trim(),
        items: form.items.trim() || undefined,
        reason: form.reason.trim() || undefined,
        locale,
        website: form.website || undefined,
      });
      setDone(r);
    } catch (err) {
      const status = (err as { response?: { status?: number } }).response?.status;
      setError(status === 429 ? t("error_limited") : t("error_generic"));
    } finally {
      setSending(false);
    }
  };

  if (done) {
    const fecha = new Intl.DateTimeFormat(locale === "pt" ? "pt-BR" : locale === "en" ? "en-US" : "es", {
      dateStyle: "long",
      timeStyle: "short",
    }).format(new Date(done.received_at));
    return (
      <div className="rounded-2xl border border-cat-boots/30 bg-cat-boots/5 p-6 text-center" role="status">
        <CheckCircle2 className="mx-auto text-cat-boots" size={36} aria-hidden />
        <h2 className="mt-3 text-xl font-bold text-brand-white">{t("done_title")}</h2>
        <p className="mt-2 text-sm text-brand-muted">
          {t("done_text", { reference: done.reference, date: fecha })}
        </p>
        <p className="mt-2 text-sm text-brand-muted">
          {done.email_sent ? t("done_email", { email: form.email.trim() }) : t("done_no_email")}
        </p>
      </div>
    );
  }

  if (step === "start") {
    return (
      <div className="rounded-2xl border border-white/10 bg-brand-card p-6 text-center">
        <p className="text-sm text-brand-muted">{t("start_text")}</p>
        <button
          type="button"
          onClick={() => setStep("form")}
          className="mt-5 inline-flex h-12 items-center justify-center rounded-xl bg-brand-red px-6 text-sm font-bold text-white transition hover:brightness-110"
        >
          {t("start_button")}
        </button>
      </div>
    );
  }

  const input =
    "w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-brand-white placeholder:text-brand-muted/60 focus:border-brand-red focus:outline-none";

  return (
    <form onSubmit={submit} noValidate className="space-y-4 rounded-2xl border border-white/10 bg-brand-card p-5 sm:p-6">
      <Field id="w-order" label={t("order_number")} hint={t("order_number_hint")}>
        <input id="w-order" className={input} value={form.order_number} onChange={set("order_number")} placeholder="FMS-0000" autoComplete="off" required />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field id="w-name" label={t("name")}>
          <input id="w-name" className={input} value={form.name} onChange={set("name")} autoComplete="name" required />
        </Field>
        <Field id="w-email" label={t("email")} hint={t("email_hint")}>
          <input id="w-email" type="email" className={input} value={form.email} onChange={set("email")} autoComplete="email" required />
        </Field>
      </div>
      <Field id="w-items" label={t("items")} hint={t("items_hint")}>
        <textarea id="w-items" rows={2} className={input} value={form.items} onChange={set("items")} />
      </Field>
      <Field id="w-reason" label={t("reason")}>
        <textarea id="w-reason" rows={2} className={input} value={form.reason} onChange={set("reason")} />
      </Field>
      {/* Trampa para bots: oculta para las personas. */}
      <input type="text" name="website" value={form.website} onChange={set("website")} tabIndex={-1} autoComplete="off" className="hidden" aria-hidden />

      {error && (
        <p className="rounded-lg border border-brand-red/30 bg-brand-red/10 px-3 py-2 text-sm text-brand-white" role="alert">
          {error}
        </p>
      )}

      <p className="text-xs leading-relaxed text-brand-muted">
        {t("legal_note")}{" "}
        <Link href="/returns-policy" className="text-brand-white underline underline-offset-2 hover:text-brand-red">
          {t("policy_link")}
        </Link>
      </p>

      <button
        type="submit"
        disabled={sending}
        className={cn(
          "inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-6 text-sm font-bold text-white transition hover:brightness-110",
          sending && "opacity-70"
        )}
      >
        {sending && <Loader2 size={16} className="animate-spin" aria-hidden />}
        {t("confirm_button")}
      </button>
    </form>
  );
}

function Field({ id, label, hint, children }: { id: string; label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-brand-white">
        {label}
      </label>
      {children}
      {hint && <p className="mt-1 text-xs text-brand-muted">{hint}</p>}
    </div>
  );
}

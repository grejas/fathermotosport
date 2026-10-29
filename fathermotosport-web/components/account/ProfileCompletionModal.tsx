"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";
import { MailCheck } from "lucide-react";
import toast from "react-hot-toast";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { PersonalDataFields, type PersonalDataValues } from "@/components/auth/PersonalDataFields";
import { completeProfile, dismissProfilePrompt, sendEmailCode, verifyEmailCode } from "@/lib/api/auth";
import { normalizePhone, validatePersonalData } from "@/lib/personalData";
import { hasErrors, type FieldErrors } from "@/lib/validators";
import { useAuthStore } from "@/store/authStore";

function apiErrors(err: unknown): { message?: string; errors?: Record<string, string[]> } | undefined {
  return (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
}

/**
 * "Completa tus datos": a clientes antiguos o de Google les faltan teléfono o país, o
 * nunca verificaron el correo. Sale UNA vez tras iniciar sesión (la API lo marca), se
 * puede cerrar y nunca aparece en el pago: no impide comprar.
 */
export function ProfileCompletionModal() {
  const t = useTranslations("profile_completion");
  const tAuth = useTranslations("auth");
  const pathname = usePathname();
  const user = useAuthStore((s) => s.user);
  const isAuth = useAuthStore((s) => s.isAuth);
  const setUser = useAuthStore((s) => s.setUser);

  const [open, setOpen] = useState(false);
  const [shownFor, setShownFor] = useState<string | null>(null);
  const [values, setValues] = useState<PersonalDataValues>({ country: "", phone: "", birth_date: "", adult_confirmed: false });
  const [errors, setErrors] = useState<FieldErrors>({});
  const [saving, setSaving] = useState(false);
  const [codeSent, setCodeSent] = useState(false);
  const [code, setCode] = useState("");
  const [verifying, setVerifying] = useState(false);
  const [sending, setSending] = useState(false);

  const enPago = /\/(checkout|auth)(\/|$)/.test(pathname ?? "");
  const needsData = !!user?.profile_incomplete;
  const needsEmail = !!user && !user.email_verified_at;

  // Se abre una sola vez por cuenta; después lo que cuenta es el estado local.
  useEffect(() => {
    if (!isAuth || !user?.id || !user.profile_prompt_due || enPago || shownFor === user.id) return;
    setValues({
      country: user.country ?? "",
      phone: user.phone ?? "",
      birth_date: user.birth_date ?? "",
      adult_confirmed: false,
    });
    setShownFor(user.id);
    setOpen(true);
  }, [isAuth, user, enPago, shownFor]);

  // Ya no falta nada: se cierra sola.
  useEffect(() => {
    if (open && user && !needsData && !needsEmail) setOpen(false);
  }, [open, user, needsData, needsEmail]);

  const close = async () => {
    setOpen(false);
    if (useAuthStore.getState().user?.profile_prompt_due) {
      try {
        const res = await dismissProfilePrompt();
        setUser(res.user);
      } catch {
        /* sin red: se volverá a ofrecer, no pasa nada */
      }
    }
  };

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    const v = validatePersonalData(values, tAuth);
    setErrors(v);
    if (hasErrors(v)) return;

    setSaving(true);
    try {
      const res = await completeProfile({
        phone: normalizePhone(values.phone) ?? values.phone,
        country: values.country,
        birth_date: values.birth_date || undefined,
        adult_confirmed: values.birth_date ? undefined : values.adult_confirmed,
      });
      setUser(res.user);
      toast.success(t("saved"));
    } catch (err) {
      const data = apiErrors(err);
      const mapped: FieldErrors = {};
      Object.entries(data?.errors ?? {}).forEach(([k, val]) => (mapped[k] = val[0]));
      setErrors(mapped);
      toast.error(data?.message ?? t("save_error"));
    } finally {
      setSaving(false);
    }
  };

  const sendCode = async () => {
    setSending(true);
    try {
      await sendEmailCode();
      setCodeSent(true);
      toast.success(t("code_sent"));
    } catch (err) {
      const data = apiErrors(err);
      toast.error(data?.errors?.email?.[0] ?? data?.message ?? t("code_error"));
    } finally {
      setSending(false);
    }
  };

  const verify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (code.length !== 6) {
      setErrors({ code: tAuth("code_required_error") });
      return;
    }
    setVerifying(true);
    try {
      const res = await verifyEmailCode(code);
      setUser(res.user);
      setErrors({});
      toast.success(t("verified"));
    } catch {
      setErrors({ code: tAuth("invalid_code_error") });
    } finally {
      setVerifying(false);
    }
  };

  if (!user) return null;

  return (
    <Modal open={open} onClose={close} title={t("title")}>
      <div className="max-h-[70vh] space-y-5 overflow-y-auto pr-1">
        <p className="text-sm text-brand-muted">{t("description")}</p>

        {needsData && (
          <form onSubmit={save} className="space-y-4">
            <PersonalDataFields values={values} errors={errors} onChange={setValues} section="required" />
            <PersonalDataFields values={values} errors={errors} onChange={setValues} section="optional" />
            <Button type="submit" variant="primary" className="w-full" loading={saving}>
              {t("save")}
            </Button>
          </form>
        )}

        {needsEmail && (
          <div className="space-y-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p className="flex items-center gap-2 text-sm font-semibold text-brand-white">
              <MailCheck size={16} className="text-brand-red" /> {t("verify_title")}
            </p>
            <p className="text-xs text-brand-muted">{t("verify_description", { email: user.email })}</p>
            {!codeSent ? (
              <Button type="button" variant="glass" className="w-full" loading={sending} onClick={sendCode}>
                {t("send_code")}
              </Button>
            ) : (
              <form onSubmit={verify} className="space-y-3">
                <Input
                  label={tAuth("code_label")}
                  name="code"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                  error={errors.code}
                  hint={tAuth("code_expires_hint")}
                  placeholder="123456"
                  maxLength={6}
                  className="text-center tracking-[0.5em]"
                />
                <Button type="submit" variant="primary" className="w-full" loading={verifying} disabled={code.length !== 6}>
                  {t("verify")}
                </Button>
                <button type="button" onClick={sendCode} disabled={sending} className="w-full text-center text-xs text-brand-muted hover:underline">
                  {t("resend_code")}
                </button>
              </form>
            )}
          </div>
        )}

        <button type="button" onClick={close} className="w-full text-center text-sm text-brand-muted hover:underline">
          {t("later")}
        </button>
      </div>
    </Modal>
  );
}

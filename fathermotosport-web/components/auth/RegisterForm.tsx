"use client";

import { useMemo, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Country } from "country-state-city";
import ReCAPTCHA from "react-google-recaptcha";
import { Check, Eye, EyeOff, X } from "lucide-react";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useSendVerificationCode, useVerifyAndRegister } from "@/lib/hooks/useAuth";
import {
  validateRegister,
  hasErrors,
  getPasswordRequirements,
  isStrongPassword,
  type FieldErrors,
} from "@/lib/validators";
import { GoogleButton } from "./GoogleButton";
import toast from "react-hot-toast";

const PASSWORD_REQUIREMENT_LABELS = {
  length: "password_req_length",
  uppercase: "password_req_uppercase",
  lowercase: "password_req_lowercase",
  number: "password_req_number",
  symbol: "password_req_symbol",
} as const;

const empty = {
  email: "",
  password: "",
  password_confirmation: "",
  first_name: "",
  last_name: "",
  birth_date: "",
  phone: "",
  country: "",
};

function extractApiError(
  err: unknown
): { message?: string; errors?: Record<string, string[]> } | undefined {
  return (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
    .response?.data;
}

export function RegisterForm() {
  const t = useTranslations("auth");
  const tCommon = useTranslations("common");
  const tCheckout = useTranslations("checkout");
  const router = useRouter();
  const sendCode = useSendVerificationCode();
  const verifyAndRegister = useVerifyAndRegister();
  const [step, setStep] = useState<"form" | "code">("form");
  const [form, setForm] = useState(empty);
  const [code, setCode] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const captchaRef = useRef<ReCAPTCHA>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const passwordRequirements = getPasswordRequirements(form.password);
  const passwordValid = isStrongPassword(form.password);
  const countries = useMemo(
    () => [...Country.getAllCountries()].sort((a, b) => a.name.localeCompare(b.name)),
    []
  );

  const update = (field: keyof typeof empty, value: string) => {
    const next = { ...form, [field]: value };
    setForm(next);
    setErrors(validateRegister(next));
  };

  const backToForm = () => {
    setStep("form");
    setCode("");
    setErrors({});
    setCaptchaToken(null);
    captchaRef.current?.reset();
  };

  const submitForm = async (e: React.FormEvent) => {
    e.preventDefault();
    const v = validateRegister(form);
    if (!form.country) v.country = t("country_required_error");
    setErrors(v);
    if (hasErrors(v)) return;

    if (!captchaToken) {
      toast.error(t("captcha_required"));
      return;
    }

    try {
      await sendCode.mutateAsync({ email: form.email, recaptcha_token: captchaToken });
      toast.success(t("code_sent"));
      setStep("code");
    } catch (err: unknown) {
      const data = extractApiError(err);
      if (data?.errors) {
        const mapped: FieldErrors = {};
        Object.entries(data.errors).forEach(([k, val]) => (mapped[k] = val[0]));
        setErrors(mapped);
      }
      toast.error(data?.message ?? t("code_send_error"));
      captchaRef.current?.reset();
      setCaptchaToken(null);
    }
  };

  const submitCode = async (e: React.FormEvent) => {
    e.preventDefault();
    if (code.length !== 6) {
      setErrors({ code: t("code_required_error") });
      return;
    }

    try {
      const res = await verifyAndRegister.mutateAsync({
        email: form.email,
        code,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        birth_date: form.birth_date || undefined,
        phone: form.phone || undefined,
        country: form.country,
        password: form.password,
        password_confirmation: form.password_confirmation,
      });
      toast.success(
        t("account_created", { amount: res.welcome_coupon?.value ?? "5" }),
        { duration: 5000 }
      );
      router.push("/profile");
    } catch (err: unknown) {
      const data = extractApiError(err);
      if (data?.errors?.code) {
        // El código no coincide o expiró: mensaje claro y específico.
        setErrors({ code: t("invalid_code_error") });
        toast.error(t("invalid_code_error"));
      } else if (data?.errors) {
        const mapped: FieldErrors = {};
        Object.entries(data.errors).forEach(([k, val]) => (mapped[k] = val[0]));
        setErrors(mapped);
        toast.error(data?.message ?? t("verify_error"));
      } else {
        toast.error(data?.message ?? t("verify_error"));
      }
    }
  };

  if (step === "code") {
    return (
      <form onSubmit={submitCode} className="space-y-4">
        <div className="text-center">
          <p className="text-sm font-semibold text-brand-white">{t("verify_code_title")}</p>
          <p className="mt-1 text-sm text-brand-muted">
            {t("verify_code_desc", { email: form.email })}
          </p>
        </div>

        <Input
          label={t("code_label")}
          name="code"
          inputMode="numeric"
          autoComplete="one-time-code"
          value={code}
          onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
          error={errors.code}
          hint={t("code_expires_hint")}
          placeholder="123456"
          maxLength={6}
          className="text-center text-lg tracking-[0.5em]"
        />

        <Button
          type="submit"
          variant="primary"
          className="w-full"
          loading={verifyAndRegister.isPending}
          disabled={code.length !== 6}
        >
          {t("verify_and_create")}
        </Button>

        <button
          type="button"
          onClick={backToForm}
          className="w-full text-center text-sm text-brand-muted hover:underline"
        >
          {t("back_to_form")}
        </button>
      </form>
    );
  }

  return (
    <form onSubmit={submitForm} className="space-y-4">
      {/* Obligatorios */}
      <Input
        label={t("email")}
        type="email"
        name="email"
        value={form.email}
        onChange={(e) => update("email", e.target.value)}
        error={errors.email}
        placeholder="tu@email.com"
      />
      <div className="grid grid-cols-2 gap-3">
        <Input
          label={tCheckout("first_name")}
          name="first_name"
          value={form.first_name}
          onChange={(e) => update("first_name", e.target.value)}
          error={errors.first_name}
          maxLength={25}
        />
        <Input
          label={tCheckout("last_name")}
          name="last_name"
          value={form.last_name}
          onChange={(e) => update("last_name", e.target.value)}
          error={errors.last_name}
          maxLength={25}
        />
      </div>
      <div>
        <label className="mb-1.5 block text-sm font-medium text-brand-white">{t("country")}</label>
        <select
          name="country"
          value={form.country}
          onChange={(e) => {
            const next = { ...form, country: e.target.value };
            setForm(next);
            setErrors({ ...errors, country: undefined });
          }}
          className="input-brand"
        >
          <option value="" disabled>
            {t("country_placeholder")}
          </option>
          {countries.map((c) => (
            <option key={c.isoCode} value={c.isoCode}>
              {c.flag} {c.name}
            </option>
          ))}
        </select>
        {errors.country && <p className="mt-1.5 text-xs text-brand-red">{errors.country}</p>}
      </div>
      <div>
        <Input
          label={t("password")}
          type={showPassword ? "text" : "password"}
          name="password"
          value={form.password}
          onChange={(e) => update("password", e.target.value)}
          rightIcon={
            <button
              type="button"
              tabIndex={-1}
              onClick={() => setShowPassword((v) => !v)}
              className="text-brand-muted hover:text-brand-white"
              aria-label={showPassword ? t("hide_password") : t("show_password")}
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          }
        />
        <ul className="mt-2 space-y-1">
          {passwordRequirements.map((req) => (
            <li
              key={req.key}
              className={`flex items-center gap-1.5 text-xs ${
                req.met ? "text-green-500" : "text-brand-muted"
              }`}
            >
              {req.met ? (
                <Check className="h-3.5 w-3.5 shrink-0" />
              ) : (
                <X className="h-3.5 w-3.5 shrink-0" />
              )}
              {t(PASSWORD_REQUIREMENT_LABELS[req.key])}
            </li>
          ))}
        </ul>
      </div>
      <Input
        label={t("confirm_password")}
        type={showPasswordConfirmation ? "text" : "password"}
        name="password_confirmation"
        value={form.password_confirmation}
        onChange={(e) => update("password_confirmation", e.target.value)}
        error={errors.password_confirmation}
        rightIcon={
          <button
            type="button"
            tabIndex={-1}
            onClick={() => setShowPasswordConfirmation((v) => !v)}
            className="text-brand-muted hover:text-brand-white"
            aria-label={showPasswordConfirmation ? t("hide_password") : t("show_password")}
          >
            {showPasswordConfirmation ? <EyeOff size={16} /> : <Eye size={16} />}
          </button>
        }
      />

      {/* Opcionales */}
      <div className="border-t border-white/10 pt-4">
        <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-brand-muted">
          {t("optional_data")}
        </p>
        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t("birth_date")}
            type="date"
            name="birth_date"
            value={form.birth_date}
            onChange={(e) => update("birth_date", e.target.value)}
            error={errors.birth_date}
            hint={t("birth_date_hint")}
            min="1926-01-01"
            max={new Date().toISOString().slice(0, 10)}
          />
          <Input
            label={tCheckout("phone")}
            name="phone"
            value={form.phone}
            onChange={(e) => update("phone", e.target.value)}
            error={errors.phone}
            placeholder="+591 68736384 o +55 11 99999-9999"
            maxLength={20}
          />
        </div>
      </div>

      <div className="flex justify-center">
        <ReCAPTCHA
          ref={captchaRef}
          sitekey={process.env.NEXT_PUBLIC_RECAPTCHA_SITE_KEY ?? ""}
          onChange={(token) => setCaptchaToken(token)}
          onExpired={() => setCaptchaToken(null)}
        />
      </div>

      <Button
        type="submit"
        variant="primary"
        className="w-full"
        loading={sendCode.isPending}
        disabled={!captchaToken || !passwordValid}
      >
        {t("send_code")}
      </Button>

      <div className="flex items-center gap-3 py-1">
        <span className="h-px flex-1 bg-white/10" />
        <span className="text-xs text-brand-muted">{tCommon("or")}</span>
        <span className="h-px flex-1 bg-white/10" />
      </div>

      <GoogleButton label={t("register_with_google")} />

      <p className="text-center text-sm text-brand-muted">
        {t("have_account")}{" "}
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          {t("login")}
        </Link>
      </p>
    </form>
  );
}

"use client";

import { useRef, useState } from "react";
import { useTranslations } from "next-intl";
import ReCAPTCHA from "react-google-recaptcha";
import { Check, X } from "lucide-react";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useRegister } from "@/lib/hooks/useAuth";
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
};

export function RegisterForm() {
  const t = useTranslations("auth");
  const tCommon = useTranslations("common");
  const tCheckout = useTranslations("checkout");
  const router = useRouter();
  const register = useRegister();
  const [form, setForm] = useState(empty);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const captchaRef = useRef<ReCAPTCHA>(null);
  const passwordRequirements = getPasswordRequirements(form.password);
  const passwordValid = isStrongPassword(form.password);

  const update = (field: keyof typeof empty, value: string) => {
    const next = { ...form, [field]: value };
    setForm(next);
    setErrors(validateRegister(next));
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const v = validateRegister(form);
    setErrors(v);
    if (hasErrors(v)) return;

    if (!captchaToken) {
      toast.error(t("captcha_required"));
      return;
    }

    try {
      const res = await register.mutateAsync({
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        birth_date: form.birth_date || undefined,
        phone: form.phone || undefined,
        recaptcha_token: captchaToken,
      });
      toast.success(
        t("account_created", { amount: res.welcome_coupon?.value ?? "5" }),
        { duration: 5000 }
      );
      router.push("/profile");
    } catch (err: unknown) {
      const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
        .response?.data;
      if (data?.errors) {
        const mapped: FieldErrors = {};
        Object.entries(data.errors).forEach(([k, val]) => (mapped[k] = val[0]));
        setErrors(mapped);
      }
      toast.error(data?.message ?? t("account_create_error"));
      captchaRef.current?.reset();
      setCaptchaToken(null);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4">
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
        <Input
          label={t("password")}
          type="password"
          name="password"
          value={form.password}
          onChange={(e) => update("password", e.target.value)}
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
        type="password"
        name="password_confirmation"
        value={form.password_confirmation}
        onChange={(e) => update("password_confirmation", e.target.value)}
        error={errors.password_confirmation}
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
            hint={t("birth_date_hint")}
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
        loading={register.isPending}
        disabled={!captchaToken || !passwordValid}
      >
        {t("register")}
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

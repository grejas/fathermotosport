"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Check, Lock, X } from "lucide-react";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { resetPassword } from "@/lib/api/auth";
import {
  getPasswordRequirements,
  isStrongPassword,
  hasErrors,
  type FieldErrors,
} from "@/lib/validators";
import toast from "react-hot-toast";

const PASSWORD_REQUIREMENT_LABELS = {
  length: "password_req_length",
  uppercase: "password_req_uppercase",
  lowercase: "password_req_lowercase",
  number: "password_req_number",
  symbol: "password_req_symbol",
} as const;

export function ResetPasswordForm() {
  const t = useTranslations("auth");
  const router = useRouter();
  const params = useSearchParams();
  const token = params.get("token");
  const email = params.get("email");

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [loading, setLoading] = useState(false);

  const passwordRequirements = getPasswordRequirements(password);
  const passwordValid = isStrongPassword(password);

  if (!token || !email) {
    return (
      <>
        <p className="mb-6 text-sm text-brand-red">{t("invalid_reset_link")}</p>
        <Link href="/forgot-password" className="font-semibold text-brand-red hover:underline">
          {t("forgot_password_title")}
        </Link>
      </>
    );
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const v: FieldErrors = {};
    if (!passwordValid) v.password = "La contraseña no cumple los requisitos.";
    if (password !== passwordConfirmation) v.password_confirmation = "Las contraseñas no coinciden.";
    setErrors(v);
    if (hasErrors(v)) return;

    setLoading(true);
    try {
      await resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      toast.success(t("reset_password_success"));
      router.push("/login");
    } catch (err: unknown) {
      const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
        .response?.data;
      if (data?.errors) {
        const mapped: FieldErrors = {};
        Object.entries(data.errors).forEach(([k, val]) => (mapped[k] = val[0]));
        setErrors(mapped);
      }
      toast.error(data?.message ?? t("reset_password_error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <p className="mb-6 text-sm text-brand-muted">{t("reset_password_desc")}</p>

      <form onSubmit={submit} className="space-y-4">
        <div>
          <Input
            label={t("password")}
            type="password"
            name="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            leftIcon={<Lock size={16} />}
            placeholder="••••••••"
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
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          error={errors.password_confirmation}
          leftIcon={<Lock size={16} />}
          placeholder="••••••••"
        />

        <Button
          type="submit"
          variant="primary"
          className="w-full"
          loading={loading}
          disabled={!passwordValid}
        >
          {t("reset_password_button")}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-brand-muted">
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          {t("back_to_login")}
        </Link>
      </p>
    </>
  );
}

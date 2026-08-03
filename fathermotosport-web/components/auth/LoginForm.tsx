"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Lock, Mail } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useLogin } from "@/lib/hooks/useAuth";
import { validateLogin, hasErrors, type FieldErrors } from "@/lib/validators";
import { GoogleButton } from "./GoogleButton";
import toast from "react-hot-toast";

export function LoginForm() {
  const t = useTranslations("auth");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const params = useSearchParams();
  const login = useLogin();
  const [form, setForm] = useState({ email: "", password: "" });
  const [errors, setErrors] = useState<FieldErrors>({});

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const v = validateLogin(form);
    setErrors(v);
    if (hasErrors(v)) return;

    try {
      await login.mutateAsync(form);
      toast.success(t("welcome_back"));
      router.push(params.get("redirect") ?? "/profile");
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } }).response?.data?.message ??
        t("invalid_credentials");
      toast.error(message);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      <Input
        label={t("email")}
        type="email"
        name="email"
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={errors.email}
        leftIcon={<Mail size={16} />}
        placeholder="tu@email.com"
      />
      <Input
        label={t("password")}
        type="password"
        name="password"
        value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        error={errors.password}
        leftIcon={<Lock size={16} />}
        placeholder="••••••••"
      />

      <div className="flex justify-end">
        <Link href="/forgot-password" className="text-sm text-brand-red hover:underline">
          {t("forgot_password")}
        </Link>
      </div>

      <Button type="submit" variant="primary" className="w-full" loading={login.isPending}>
        {t("login")}
      </Button>

      <div className="flex items-center gap-3 py-1">
        <span className="h-px flex-1 bg-white/10" />
        <span className="text-xs text-brand-muted">{tCommon("or")}</span>
        <span className="h-px flex-1 bg-white/10" />
      </div>

      <GoogleButton label={t("login_with_google")} />

      <p className="text-center text-sm text-brand-muted">
        {t("no_account")}{" "}
        <Link href="/register" className="font-semibold text-brand-red hover:underline">
          {t("register")}
        </Link>
      </p>
    </form>
  );
}

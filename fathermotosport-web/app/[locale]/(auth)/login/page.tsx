"use client";

import { Suspense } from "react";
import { useTranslations } from "next-intl";
import { LoginForm } from "@/components/auth/LoginForm";
import { Spinner } from "@/components/ui/Spinner";

export default function LoginPage() {
  const t = useTranslations("auth");

  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">{t("login")}</h1>
      <p className="mb-6 text-sm text-brand-muted">{t("welcome_subtitle")}</p>
      <Suspense fallback={<Spinner />}>
        <LoginForm />
      </Suspense>
    </>
  );
}

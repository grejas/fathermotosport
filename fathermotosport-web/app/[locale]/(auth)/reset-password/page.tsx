"use client";

import { Suspense } from "react";
import { useTranslations } from "next-intl";
import { ResetPasswordForm } from "@/components/auth/ResetPasswordForm";
import { Spinner } from "@/components/ui/Spinner";

export default function ResetPasswordPage() {
  const t = useTranslations("auth");

  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">{t("reset_password_title")}</h1>
      <Suspense fallback={<Spinner />}>
        <ResetPasswordForm />
      </Suspense>
    </>
  );
}

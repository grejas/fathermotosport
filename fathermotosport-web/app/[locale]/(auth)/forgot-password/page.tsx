"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { Mail } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { forgotPassword } from "@/lib/api/auth";
import { isEmail } from "@/lib/validators";
import toast from "react-hot-toast";

export default function ForgotPasswordPage() {
  const t = useTranslations("auth");
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isEmail(email)) {
      toast.error(t("invalid_email"));
      return;
    }
    setLoading(true);
    try {
      await forgotPassword(email);
      setSent(true);
      toast.success(t("reset_email_sent"));
    } catch {
      toast.error(t("reset_email_error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">{t("forgot_password_title")}</h1>
      <p className="mb-6 text-sm text-brand-muted">
        {t("forgot_password_desc")}
      </p>

      {sent ? (
        <p className="rounded-xl border border-cat-boots/30 bg-cat-boots/10 p-4 text-sm text-cat-boots">
          {t("check_inbox")}
        </p>
      ) : (
        <form onSubmit={submit} className="space-y-4">
          <Input
            label={t("email")}
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            leftIcon={<Mail size={16} />}
            placeholder="tu@email.com"
          />
          <Button type="submit" variant="primary" className="w-full" loading={loading}>
            {t("send_link")}
          </Button>
        </form>
      )}

      <p className="mt-6 text-center text-sm text-brand-muted">
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          {t("back_to_login")}
        </Link>
      </p>
    </>
  );
}

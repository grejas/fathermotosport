"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { KeyRound, UserPlus } from "lucide-react";
import { Link, useRouter } from "@/lib/i18n/navigation";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { registerFromOrder } from "@/lib/api/auth";
import { useAuthStore } from "@/store/authStore";
import { isStrongPassword } from "@/lib/validators";
import toast from "react-hot-toast";

interface Props {
  orderId: string;
  accessToken: string;
  customerName: string | null;
  customerEmail: string | null;
}

/**
 * Se ofrece tras pagar como invitado: el pedido ya tiene nombre y email, así que
 * solo falta una contraseña. Al crearla, ese pedido (y los anteriores del mismo
 * email verificado) quedan vinculados a la cuenta nueva.
 */
export function CreateAccountFromOrder({ orderId, accessToken, customerName, customerEmail }: Props) {
  const t = useTranslations("checkout");
  const tAuth = useTranslations("auth");
  const router = useRouter();
  const registerUser = useAuthStore((s) => s.registerUser);

  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [yaTieneCuenta, setYaTieneCuenta] = useState(false);
  const [loading, setLoading] = useState(false);

  const crear = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!isStrongPassword(password)) {
      setError(tAuth("password_requirements_error"));
      return;
    }
    if (password !== confirmation) {
      setError(tAuth("passwords_dont_match"));
      return;
    }

    setLoading(true);
    try {
      const res = await registerFromOrder({
        orderId,
        accessToken,
        password,
        passwordConfirmation: confirmation,
      });

      registerUser({ user: res.user, token: res.token });
      toast.success(t("account_created"));
      router.push("/orders");
    } catch (err: unknown) {
      const data = (err as { response?: { data?: { message?: string; should_login?: boolean } } })
        .response?.data;
      // Email con cuenta previa: no se duplica, se invita a iniciar sesión.
      if (data?.should_login) setYaTieneCuenta(true);
      setError(data?.message ?? t("account_error"));
      setLoading(false);
    }
  };

  if (yaTieneCuenta) {
    return (
      <div className="mt-6 rounded-2xl border border-white/10 bg-brand-card p-5 text-left">
        <p className="text-sm text-brand-muted">{error}</p>
        <Link href={`/login?redirect=/orders`} className="mt-3 block">
          <Button variant="primary" className="w-full">
            {tAuth("login")}
          </Button>
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={crear} className="mt-6 rounded-2xl border border-white/10 bg-brand-card p-5 text-left">
      <p className="flex items-center gap-2 text-sm font-semibold text-brand-white">
        <UserPlus size={16} className="text-cat-boots" />
        {t("account_have_your_data")}
      </p>
      <p className="mt-1 text-xs text-brand-muted">
        {[customerName, customerEmail].filter(Boolean).join(" · ")}
      </p>
      <p className="mt-2 text-xs text-brand-muted">{t("account_invite")}</p>

      <div className="mt-4 space-y-3">
        <Input
          label={tAuth("password")}
          name="password"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          hint={tAuth("password_hint")}
        />
        <Input
          label={tAuth("confirm_password")}
          name="password_confirmation"
          type="password"
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
          error={error ?? undefined}
        />
      </div>

      <Button
        type="submit"
        variant="primary"
        className="mt-4 w-full"
        loading={loading}
        icon={<KeyRound size={16} />}
      >
        {t("account_create")}
      </Button>
    </form>
  );
}

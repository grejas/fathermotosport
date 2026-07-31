"use client";

import { useState } from "react";
import Link from "next/link";
import { Mail } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { forgotPassword } from "@/lib/api/auth";
import { isEmail } from "@/lib/validators";
import toast from "react-hot-toast";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isEmail(email)) {
      toast.error("Ingresa un email válido.");
      return;
    }
    setLoading(true);
    try {
      await forgotPassword(email);
      setSent(true);
      toast.success("Si el email existe, recibirás instrucciones.");
    } catch {
      toast.error("No se pudo enviar el correo.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">Recuperar contraseña</h1>
      <p className="mb-6 text-sm text-brand-muted">
        Te enviaremos un enlace para restablecer tu contraseña.
      </p>

      {sent ? (
        <p className="rounded-xl border border-cat-boots/30 bg-cat-boots/10 p-4 text-sm text-cat-boots">
          Revisa tu bandeja de entrada para continuar.
        </p>
      ) : (
        <form onSubmit={submit} className="space-y-4">
          <Input
            label="Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            leftIcon={<Mail size={16} />}
            placeholder="tu@email.com"
          />
          <Button type="submit" variant="primary" className="w-full" loading={loading}>
            Enviar enlace
          </Button>
        </form>
      )}

      <p className="mt-6 text-center text-sm text-brand-muted">
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          Volver a ingresar
        </Link>
      </p>
    </>
  );
}

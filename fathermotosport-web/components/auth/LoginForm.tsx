"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Lock, Mail } from "lucide-react";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useLogin } from "@/lib/hooks/useAuth";
import { validateLogin, hasErrors, type FieldErrors } from "@/lib/validators";
import toast from "react-hot-toast";

export function LoginForm() {
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
      toast.success("¡Bienvenido de vuelta!");
      router.push(params.get("redirect") ?? "/profile");
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "Credenciales incorrectas.";
      toast.error(message);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      <Input
        label="Email"
        type="email"
        name="email"
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={errors.email}
        leftIcon={<Mail size={16} />}
        placeholder="tu@email.com"
      />
      <Input
        label="Contraseña"
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
          ¿Olvidaste tu contraseña?
        </Link>
      </div>

      <Button type="submit" variant="primary" className="w-full" loading={login.isPending}>
        Ingresar
      </Button>

      <p className="text-center text-sm text-brand-muted">
        ¿No tienes cuenta?{" "}
        <Link href="/register" className="font-semibold text-brand-red hover:underline">
          Crear cuenta
        </Link>
      </p>
    </form>
  );
}

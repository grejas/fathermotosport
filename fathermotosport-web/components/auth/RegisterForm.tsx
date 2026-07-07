"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useRegister } from "@/lib/hooks/useAuth";
import { validateRegister, hasErrors, type FieldErrors } from "@/lib/validators";
import toast from "react-hot-toast";

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
  const router = useRouter();
  const register = useRegister();
  const [form, setForm] = useState(empty);
  const [errors, setErrors] = useState<FieldErrors>({});

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

    try {
      // Solo se envían los campos opcionales que el usuario completó.
      const res = await register.mutateAsync({
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
        first_name: form.first_name || undefined,
        last_name: form.last_name || undefined,
        birth_date: form.birth_date || undefined,
        phone: form.phone || undefined,
      });
      toast.success(
        `¡Cuenta creada! Tienes un cupón de $${res.welcome_coupon?.value ?? "5"} esperándote 🎉`,
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
      toast.error(data?.message ?? "No se pudo crear la cuenta.");
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      {/* Obligatorios */}
      <Input
        label="Email"
        type="email"
        name="email"
        value={form.email}
        onChange={(e) => update("email", e.target.value)}
        error={errors.email}
        placeholder="tu@email.com"
      />
      <Input
        label="Contraseña"
        type="password"
        name="password"
        value={form.password}
        onChange={(e) => update("password", e.target.value)}
        error={errors.password}
        hint="Mínimo 8 caracteres"
      />
      <Input
        label="Confirmar contraseña"
        type="password"
        name="password_confirmation"
        value={form.password_confirmation}
        onChange={(e) => update("password_confirmation", e.target.value)}
        error={errors.password_confirmation}
      />

      {/* Opcionales */}
      <div className="border-t border-white/10 pt-4">
        <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-brand-muted">
          Datos opcionales
        </p>
        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Nombre"
            name="first_name"
            value={form.first_name}
            onChange={(e) => update("first_name", e.target.value)}
            placeholder="Opcional"
          />
          <Input
            label="Apellido"
            name="last_name"
            value={form.last_name}
            onChange={(e) => update("last_name", e.target.value)}
            placeholder="Opcional"
          />
        </div>
        <div className="mt-3 grid grid-cols-2 gap-3">
          <Input
            label="Fecha de nacimiento"
            type="date"
            name="birth_date"
            value={form.birth_date}
            onChange={(e) => update("birth_date", e.target.value)}
            hint="Para ofertas personalizadas"
          />
          <Input
            label="Teléfono"
            name="phone"
            value={form.phone}
            onChange={(e) => update("phone", e.target.value)}
            placeholder="Opcional"
          />
        </div>
      </div>

      <Button type="submit" variant="primary" className="w-full" loading={register.isPending}>
        Crear cuenta
      </Button>

      {/* Google (visual por ahora) */}
      <div className="text-center">
        <p className="text-xs text-brand-muted">
          ¿Tenés cuenta de Google?{" "}
          <button
            type="button"
            // TODO: implementar OAuth Google en Fase 6
            onClick={() => toast("Inicio con Google disponible próximamente.", { icon: "🔒" })}
            className="font-semibold text-brand-red hover:underline"
          >
            Continuar con Google
          </button>
        </p>
      </div>

      <p className="text-center text-sm text-brand-muted">
        ¿Ya tienes cuenta?{" "}
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          Ingresar
        </Link>
      </p>
    </form>
  );
}

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
  first_name: "",
  last_name: "",
  email: "",
  phone: "",
  password: "",
  password_confirmation: "",
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
      const res = await register.mutateAsync(form);
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
      <div className="grid grid-cols-2 gap-3">
        <Input
          label="Nombre"
          name="first_name"
          value={form.first_name}
          onChange={(e) => update("first_name", e.target.value)}
          error={errors.first_name}
        />
        <Input
          label="Apellido"
          name="last_name"
          value={form.last_name}
          onChange={(e) => update("last_name", e.target.value)}
          error={errors.last_name}
        />
      </div>
      <Input
        label="Email"
        type="email"
        name="email"
        value={form.email}
        onChange={(e) => update("email", e.target.value)}
        error={errors.email}
      />
      <Input
        label="Teléfono (opcional)"
        name="phone"
        value={form.phone}
        onChange={(e) => update("phone", e.target.value)}
        error={errors.phone}
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

      <Button type="submit" variant="primary" className="w-full" loading={register.isPending}>
        Crear cuenta
      </Button>

      <p className="text-center text-sm text-brand-muted">
        ¿Ya tienes cuenta?{" "}
        <Link href="/login" className="font-semibold text-brand-red hover:underline">
          Ingresar
        </Link>
      </p>
    </form>
  );
}

import { RegisterForm } from "@/components/auth/RegisterForm";

export default function RegisterPage() {
  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">Crear cuenta</h1>
      <p className="mb-6 text-sm text-brand-muted">
        Regístrate y recibe un <span className="text-brand-gold">cupón de $5</span> de bienvenida 🎉
      </p>
      <RegisterForm />
    </>
  );
}

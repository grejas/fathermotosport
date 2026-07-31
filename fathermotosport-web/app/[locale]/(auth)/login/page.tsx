import { Suspense } from "react";
import { LoginForm } from "@/components/auth/LoginForm";
import { Spinner } from "@/components/ui/Spinner";

export default function LoginPage() {
  return (
    <>
      <h1 className="mb-1 text-2xl font-extrabold text-brand-white">Ingresar</h1>
      <p className="mb-6 text-sm text-brand-muted">Bienvenido de vuelta a FatherMotoSport</p>
      <Suspense fallback={<Spinner />}>
        <LoginForm />
      </Suspense>
    </>
  );
}

import Link from "next/link";
import { Button } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-4 text-center">
      <p className="text-7xl font-extrabold text-brand-red">404</p>
      <h1 className="mt-4 text-2xl font-bold text-brand-white">Página no encontrada</h1>
      <p className="mt-2 max-w-md text-brand-muted">
        La ruta que buscas no existe o fue movida. Vuelve al inicio para seguir explorando.
      </p>
      <Link href="/" className="mt-6">
        <Button variant="primary">Volver al inicio</Button>
      </Link>
    </div>
  );
}

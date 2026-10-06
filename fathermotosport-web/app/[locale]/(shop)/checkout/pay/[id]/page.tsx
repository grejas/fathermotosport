import { Suspense } from "react";
import { Spinner } from "@/components/ui/Spinner";
import { PayPendingClient } from "./PayPendingClient";

/** Retomar el pago de un pedido pendiente: el enlace del correo de recuperación. */
export default function PayPendingPage({ params }: { params: { id: string } }) {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[70vh] items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <PayPendingClient id={params.id} />
    </Suspense>
  );
}

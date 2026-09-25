import { Suspense } from "react";
import { Spinner } from "@/components/ui/Spinner";
import { PaypalCancelClient } from "./PaypalCancelClient";

export default function PaypalCancelPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[70vh] items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <PaypalCancelClient />
    </Suspense>
  );
}

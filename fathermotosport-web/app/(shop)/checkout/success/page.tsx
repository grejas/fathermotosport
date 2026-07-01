import { Suspense } from "react";
import { SuccessClient } from "./SuccessClient";
import { Spinner } from "@/components/ui/Spinner";

export default function CheckoutSuccessPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <SuccessClient />
    </Suspense>
  );
}

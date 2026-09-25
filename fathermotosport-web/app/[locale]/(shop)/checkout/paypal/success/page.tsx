import { Suspense } from "react";
import { Spinner } from "@/components/ui/Spinner";
import { PaypalSuccessClient } from "./PaypalSuccessClient";

export default function PaypalSuccessPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[70vh] items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <PaypalSuccessClient />
    </Suspense>
  );
}

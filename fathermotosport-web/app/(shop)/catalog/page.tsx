import { Suspense } from "react";
import { CatalogClient } from "./CatalogClient";
import { Spinner } from "@/components/ui/Spinner";

export default function CatalogPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center">
          <Spinner size={32} />
        </div>
      }
    >
      <CatalogClient />
    </Suspense>
  );
}

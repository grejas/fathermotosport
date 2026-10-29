import type { Metadata } from "next";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { Spinner } from "@/components/ui/Spinner";
import { UnsubscribeClient } from "./UnsubscribeClient";

export async function generateMetadata({ params: { locale } }: { params: { locale: string } }): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "unsubscribe" });

  // Página personal (lleva el enlace firmado): no se indexa.
  return { title: t("title"), robots: { index: false, follow: false } };
}

/** Baja de las promociones: el enlace del pie de los correos promocionales. */
export default function UnsubscribePage() {
  return (
    <div className="mx-auto flex min-h-[70vh] max-w-md items-center px-4 pb-16 pt-24">
      <div className="w-full rounded-2xl border border-white/10 bg-brand-card p-6 sm:p-8">
        <Suspense
          fallback={
            <div className="flex justify-center py-10">
              <Spinner size={32} />
            </div>
          }
        >
          <UnsubscribeClient />
        </Suspense>
      </div>
    </div>
  );
}

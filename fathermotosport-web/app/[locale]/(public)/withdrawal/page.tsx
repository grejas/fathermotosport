import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { WithdrawalForm } from "@/components/legal/WithdrawalForm";

export async function generateMetadata({ params: { locale } }: { params: { locale: string } }): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "withdrawal" });
  return { title: t("title"), description: t("intro"), robots: { index: false } };
}

/** "Desistir de una compra": función de desistimiento exigida en la UE y Brasil. */
export default async function WithdrawalPage({ params: { locale } }: { params: { locale: string } }) {
  const t = await getTranslations({ locale, namespace: "withdrawal" });

  return (
    <div className="mx-auto max-w-2xl px-4 pb-16 pt-20 sm:px-6 sm:pt-24">
      <h1 className="text-3xl font-extrabold text-brand-white sm:text-4xl">{t("title")}</h1>
      <p className="mt-3 text-sm leading-relaxed text-brand-muted">{t("intro")}</p>
      <div className="mt-6">
        <WithdrawalForm />
      </div>
    </div>
  );
}

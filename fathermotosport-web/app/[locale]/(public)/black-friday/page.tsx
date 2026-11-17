import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { BlackFridayClient } from "./BlackFridayClient";

export async function generateMetadata({
  params: { locale },
}: {
  params: { locale: string };
}): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "black_friday" });
  return {
    title: t("meta_title"),
    description: t("meta_description"),
    openGraph: { title: t("meta_title"), description: t("meta_description") },
  };
}

export default function BlackFridayPage() {
  return <BlackFridayClient />;
}

import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { BlackFridayClient } from "./BlackFridayClient";
import { alternatesFor, ogLocale } from "@/lib/seo";

export async function generateMetadata({
  params: { locale },
}: {
  params: { locale: string };
}): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "black_friday" });
  return {
    title: t("meta_title"),
    description: t("meta_description"),
    alternates: alternatesFor("/black-friday", locale),
    openGraph: { title: t("meta_title"), description: t("meta_description"), locale: ogLocale(locale) },
  };
}

export default function BlackFridayPage() {
  return <BlackFridayClient />;
}

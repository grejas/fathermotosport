import { Suspense } from "react";
import { CatalogClient } from "./CatalogClient";
import { Spinner } from "@/components/ui/Spinner";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { categoryLabel, getMenuCategories } from "@/lib/api/categories";
import { alternatesFor, ogLocale } from "@/lib/seo";

/**
 * Título propio por categoría (?category=1 → "Cascos | FatherMotoSport") en vez del
 * genérico. El canonical conserva solo la categoría; orden, página y filtros no cuentan.
 */
export async function generateMetadata({
  params,
  searchParams,
}: {
  params: { locale: string };
  searchParams: { category?: string };
}): Promise<Metadata> {
  const [t, tNav, categories] = await Promise.all([
    getTranslations({ locale: params.locale, namespace: "catalog" }),
    getTranslations({ locale: params.locale, namespace: "nav" }),
    getMenuCategories(),
  ]);
  const id = Number(searchParams.category);
  const cat = Number.isFinite(id) ? categories.find((c) => Number(c.id) === id) : undefined;
  const nombre = cat ? categoryLabel(tNav, cat) : t("title");
  const path = cat ? `/catalog?category=${cat.id}` : "/catalog";
  const description = cat
    ? `${nombre} originales con envío a toda América y Europa. AGV, Shoei, Shark, Alpinestars y más en FatherMotoSport.`
    : "Catálogo completo: cascos, guantes, botas, chaquetas y accesorios para moto. AGV, Shoei, Shark, Alpinestars y más.";
  return {
    title: nombre,
    description,
    alternates: alternatesFor(path, params.locale),
    openGraph: { title: nombre, description, locale: ogLocale(params.locale) },
  };
}

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

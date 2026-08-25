"use client";

import { useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useRouter } from "@/lib/i18n/navigation";
import { useQuery } from "@tanstack/react-query";
import { LayoutGrid, List } from "lucide-react";
import { getProducts } from "@/lib/api/products";
import { getBrands } from "@/lib/api/catalog";
import { ProductGrid } from "@/components/product/ProductGrid";
import { CatalogFilters, type CatalogFilterState } from "@/components/product/CatalogFilters";
import type { ProductFilters } from "@/lib/types";

const emptyFilters: CatalogFilterState = {
  type: "todos",
  brands: [],
  minPrice: "",
  maxPrice: "",
  certifications: [],
};

export function CatalogClient() {
  const t = useTranslations("catalog");
  const router = useRouter();
  const params = useSearchParams();
  const [view, setView] = useState<"grid" | "list">("grid");
  const [sort, setSort] = useState<ProductFilters["sort"]>("newest");
  const [filters, setFilters] = useState<CatalogFilterState>(emptyFilters);
  const [page, setPage] = useState(1);

  const category = params.get("category") ?? undefined;
  const search = params.get("search") ?? undefined;

  const queryFilters: ProductFilters = useMemo(
    () => ({
      category,
      search,
      brand: filters.brands[0],
      min_price: filters.minPrice ? Number(filters.minPrice) : undefined,
      max_price: filters.maxPrice ? Number(filters.maxPrice) : undefined,
      sort,
      page,
      per_page: 12,
    }),
    [category, search, filters, sort, page]
  );

  const { data, isLoading } = useQuery({
    queryKey: ["catalog", queryFilters],
    queryFn: () => getProducts(queryFilters),
    staleTime: 30000,
    refetchOnMount: true,
    refetchOnWindowFocus: true,
  });

  const { data: brands } = useQuery({ queryKey: ["brands"], queryFn: getBrands });

  const products = data?.data ?? [];
  const meta = data?.meta;

  const clearFilters = () => {
    setFilters(emptyFilters);
    setPage(1);
    router.push("/catalog");
  };

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 pb-16 pt-24 sm:px-6 lg:flex-row">
      <CatalogFilters
        brands={brands ?? []}
        value={filters}
        onChange={(f) => {
          setFilters(f);
          setPage(1);
        }}
        onClear={clearFilters}
      />

      <div className="flex-1">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-brand-white">
              {search ? `Resultados para "${search}"` : t("title")}
            </h1>
            <p className="text-sm text-brand-muted">
              {meta?.total ?? 0} {t("products")}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value as ProductFilters["sort"])}
              className="input-brand w-auto px-3 py-2 text-sm"
            >
              <option value="newest">{t("newest")}</option>
              <option value="price_asc">{t("price_asc")}</option>
              <option value="price_desc">{t("price_desc")}</option>
              <option value="name_asc">{t("name_az")}</option>
              <option value="popular">{t("popular")}</option>
            </select>

            <div className="flex rounded-lg border border-white/10">
              <button
                onClick={() => setView("grid")}
                className={`p-2 ${view === "grid" ? "text-brand-red" : "text-brand-muted"}`}
                aria-label="Vista cuadrícula"
              >
                <LayoutGrid size={18} />
              </button>
              <button
                onClick={() => setView("list")}
                className={`p-2 ${view === "list" ? "text-brand-red" : "text-brand-muted"}`}
                aria-label="Vista lista"
              >
                <List size={18} />
              </button>
            </div>
          </div>
        </div>

        <ProductGrid products={products} loading={isLoading} />

        {meta && meta.last_page > 1 && (
          <div className="mt-10 flex justify-center gap-2">
            {Array.from({ length: meta.last_page }).map((_, i) => (
              <button
                key={i}
                onClick={() => setPage(i + 1)}
                className={`h-9 w-9 rounded-lg text-sm font-semibold transition ${
                  meta.current_page === i + 1
                    ? "bg-brand-red text-white"
                    : "border border-white/10 text-brand-muted hover:text-brand-white"
                }`}
              >
                {i + 1}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

import { useQuery } from "@tanstack/react-query";
import * as productsApi from "@/lib/api/products";
import type { ProductFilters } from "@/lib/types";

export function useProducts(filters: ProductFilters = {}) {
  return useQuery({
    queryKey: ["products", filters],
    queryFn: () => productsApi.getProducts(filters),
  });
}

export function useFeaturedProducts() {
  return useQuery({
    queryKey: ["products", "featured"],
    queryFn: productsApi.getFeaturedProducts,
  });
}

export function useProduct(slug: string) {
  return useQuery({
    queryKey: ["product", slug],
    queryFn: () => productsApi.getProductBySlug(slug),
    enabled: !!slug,
  });
}

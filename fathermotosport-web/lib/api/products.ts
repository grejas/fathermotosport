import apiClient from "./client";
import type { PaginatedResponse, Product, ProductFilters } from "@/lib/types";

export async function getProducts(filters: ProductFilters = {}): Promise<PaginatedResponse<Product>> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>("/products", { params: filters });
  return data;
}

export async function getFeaturedProducts(): Promise<Product[]> {
  const { data } = await apiClient.get<{ data: Product[] }>("/products/featured");
  return data.data;
}

export async function getProductBySlug(slug: string): Promise<Product> {
  const { data } = await apiClient.get<{ data: Product }>(`/products/${slug}`);
  return data.data;
}

export async function searchProducts(query: string): Promise<PaginatedResponse<Product>> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>("/products/search", {
    params: { q: query },
  });
  return data;
}

export async function getRelatedProducts(categoryId: number, excludeSlug: string): Promise<Product[]> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>("/products", {
    params: { category: categoryId, per_page: 4 },
  });
  return data.data.filter((p) => p.slug !== excludeSlug).slice(0, 4);
}

import apiClient from "./client";
import type { Brand, Category } from "@/lib/types";

export async function getCategories(): Promise<Category[]> {
  const { data } = await apiClient.get<{ data: Category[] }>("/categories");
  return data.data;
}

export async function getBrands(): Promise<Brand[]> {
  const { data } = await apiClient.get<{ data: Brand[] }>("/brands");
  return data.data;
}

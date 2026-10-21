import apiClient from "./client";
import type { PaginatedResponse, Review, ReviewsSummary } from "@/lib/types";

export interface CreateReviewPayload {
  rating: number;
  title?: string;
  comment?: string;
  /** Hasta 3 fotos ya comprimidas en el navegador (ver lib/utils/comprimirImagen.ts). */
  photos?: File[];
}

export async function getReviews(page = 1, perPage = 60): Promise<PaginatedResponse<Review>> {
  const { data } = await apiClient.get<PaginatedResponse<Review>>("/reviews", {
    params: { page, per_page: perPage },
  });
  return data;
}

export async function getReviewsSummary(): Promise<ReviewsSummary> {
  const { data } = await apiClient.get<ReviewsSummary>("/reviews/summary");
  return data;
}

export async function createReview(slug: string, payload: CreateReviewPayload): Promise<Review> {
  const { photos = [], ...campos } = payload;

  if (photos.length === 0) {
    const { data } = await apiClient.post<{ data: Review }>(`/products/${slug}/reviews`, campos);
    return data.data;
  }

  // Con fotos va como multipart. El Content-Type explícito evita que axios convierta el
  // FormData a JSON (el cliente tiene application/json por defecto); el navegador
  // completa el boundary.
  const form = new FormData();
  form.append("rating", String(campos.rating));
  if (campos.title) form.append("title", campos.title);
  if (campos.comment) form.append("comment", campos.comment);
  photos.forEach((foto) => form.append("photos[]", foto, foto.name));

  const { data } = await apiClient.post<{ data: Review }>(`/products/${slug}/reviews`, form, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return data.data;
}

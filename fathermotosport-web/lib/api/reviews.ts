import apiClient from "./client";
import type { PaginatedResponse, Review, ReviewsSummary } from "@/lib/types";

export interface CreateReviewPayload {
  rating: number;
  title?: string;
  comment?: string;
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
  const { data } = await apiClient.post<{ data: Review }>(`/products/${slug}/reviews`, payload);
  return data.data;
}

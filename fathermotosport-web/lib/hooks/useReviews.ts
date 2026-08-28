import { useQuery } from "@tanstack/react-query";
import * as reviewsApi from "@/lib/api/reviews";

export function useReviews(page = 1, perPage = 60) {
  return useQuery({
    queryKey: ["reviews", page, perPage],
    queryFn: () => reviewsApi.getReviews(page, perPage),
  });
}

export function useReviewsSummary() {
  return useQuery({
    queryKey: ["reviews", "summary"],
    queryFn: reviewsApi.getReviewsSummary,
  });
}

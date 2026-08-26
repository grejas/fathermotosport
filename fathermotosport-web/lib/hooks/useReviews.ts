import { useQuery } from "@tanstack/react-query";
import * as reviewsApi from "@/lib/api/reviews";

export function useReviews(page = 1) {
  return useQuery({
    queryKey: ["reviews", page],
    queryFn: () => reviewsApi.getReviews(page),
  });
}

export function useReviewsSummary() {
  return useQuery({
    queryKey: ["reviews", "summary"],
    queryFn: reviewsApi.getReviewsSummary,
  });
}

import { useMutation } from "@tanstack/react-query";
import * as reviewsApi from "@/lib/api/reviews";
import type { CreateReviewPayload } from "@/lib/api/reviews";

export function useCreateReview() {
  return useMutation({
    mutationFn: ({ slug, ...payload }: CreateReviewPayload & { slug: string }) =>
      reviewsApi.createReview(slug, payload),
  });
}

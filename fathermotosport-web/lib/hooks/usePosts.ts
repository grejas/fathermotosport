import { useQuery } from "@tanstack/react-query";
import * as postsApi from "@/lib/api/posts";

export function usePosts(page = 1) {
  return useQuery({
    queryKey: ["posts", page],
    queryFn: () => postsApi.getPosts(page),
  });
}

export function usePost(slug: string) {
  return useQuery({
    queryKey: ["post", slug],
    queryFn: () => postsApi.getPostBySlug(slug),
    enabled: !!slug,
  });
}

import apiClient from "./client";
import type { PaginatedResponse, Post } from "@/lib/types";

export async function getPosts(page = 1): Promise<PaginatedResponse<Post>> {
  const { data } = await apiClient.get<PaginatedResponse<Post>>("/posts", { params: { page } });
  return data;
}

export async function getPostBySlug(slug: string): Promise<Post> {
  const { data } = await apiClient.get<{ data: Post }>(`/posts/${slug}`);
  return data.data;
}

import { cache } from "react";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getPostBySlug } from "@/lib/api/posts";
import { getImageUrl } from "@/lib/utils";
import { BlogPostClient } from "./BlogPostClient";

const getPost = cache(async (slug: string) => {
  try {
    return await getPostBySlug(slug);
  } catch {
    return null;
  }
});

export async function generateMetadata({ params }: { params: { slug: string } }): Promise<Metadata> {
  const post = await getPost(params.slug);
  if (!post) return {};

  const description = post.excerpt ?? undefined;

  return {
    title: post.title,
    description,
    openGraph: {
      title: post.title,
      description,
      type: "article",
      images: post.cover_image ? [{ url: getImageUrl(post.cover_image) }] : undefined,
    },
  };
}

export default async function BlogPostPage({ params }: { params: { slug: string } }) {
  const post = await getPost(params.slug);
  if (!post) notFound();

  return <BlogPostClient post={post} />;
}

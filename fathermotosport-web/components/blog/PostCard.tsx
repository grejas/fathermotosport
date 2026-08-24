"use client";

import Image from "next/image";
import { Link } from "@/lib/i18n/navigation";
import { Newspaper } from "lucide-react";
import type { Post } from "@/lib/types";
import { getImageUrl, formatDate, truncate } from "@/lib/utils";

export function PostCard({ post }: { post: Post }) {
  return (
    <Link href={`/blog/${post.slug}`} className="group" aria-label={post.title}>
      <article className="card-product h-full">
        <div className="relative aspect-[16/10] overflow-hidden bg-brand-dark">
          {post.cover_image ? (
            <Image
              src={getImageUrl(post.cover_image)}
              alt={post.title}
              fill
              sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
              className="object-cover transition-transform duration-500 group-hover:scale-105"
            />
          ) : (
            <div className="flex h-full items-center justify-center">
              <Newspaper size={40} className="text-brand-red opacity-30" />
            </div>
          )}
        </div>

        <div className="p-4">
          <h3 className="line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-brand-white sm:text-base">
            {post.title}
          </h3>

          {post.excerpt && (
            <p className="mt-2 line-clamp-2 text-xs text-brand-muted sm:text-sm">{truncate(post.excerpt, 140)}</p>
          )}

          <div className="mt-3 flex items-center gap-2 text-xs text-brand-muted">
            {post.author && <span>{post.author.name}</span>}
            {post.author && post.published_at && <span aria-hidden>·</span>}
            {post.published_at && <span>{formatDate(post.published_at)}</span>}
          </div>
        </div>
      </article>
    </Link>
  );
}

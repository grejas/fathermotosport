"use client";

import { motion } from "framer-motion";
import type { Post } from "@/lib/types";
import { stagger, slideUp } from "@/animations/variants";
import { Skeleton } from "@/components/ui/Skeleton";
import { PostCard } from "./PostCard";

interface PostGridProps {
  posts: Post[];
  loading?: boolean;
  emptyMessage?: string;
}

export function PostGrid({ posts, loading, emptyMessage }: PostGridProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-2 gap-5 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="card-product p-0">
            <Skeleton className="aspect-[16/10] w-full rounded-b-none" />
            <div className="space-y-2 p-4">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-3 w-full" />
              <Skeleton className="h-3 w-1/2" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (!posts.length) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-brand-muted">
        {emptyMessage ?? "Todavía no hay artículos publicados."}
      </div>
    );
  }

  return (
    <motion.div
      variants={stagger}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-50px" }}
      className="grid grid-cols-2 gap-5 lg:grid-cols-3"
    >
      {posts.map((post) => (
        <motion.div key={post.id} variants={slideUp}>
          <PostCard post={post} />
        </motion.div>
      ))}
    </motion.div>
  );
}

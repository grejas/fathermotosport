"use client";

import { useState } from "react";
import { Bebas_Neue } from "next/font/google";
import { PostGrid } from "@/components/blog/PostGrid";
import { usePosts } from "@/lib/hooks/usePosts";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

export function BlogClient() {
  const [page, setPage] = useState(1);
  const { data, isLoading } = usePosts(page);

  const posts = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div className="mx-auto max-w-7xl px-4 pb-16 pt-24 sm:px-6">
      <div className="mb-8">
        <h1 className={`${bebasNeue.className} text-4xl uppercase tracking-wide text-brand-white sm:text-5xl`}>
          Blog
        </h1>
        <p className="mt-1 text-sm text-brand-muted">Noticias, guías y novedades de FatherMotoSport.</p>
      </div>

      <PostGrid posts={posts} loading={isLoading} />

      {meta && meta.last_page > 1 && (
        <div className="mt-10 flex justify-center gap-2">
          {Array.from({ length: meta.last_page }).map((_, i) => (
            <button
              key={i}
              onClick={() => setPage(i + 1)}
              className={`h-9 w-9 rounded-lg text-sm font-semibold transition ${
                meta.current_page === i + 1
                  ? "bg-brand-red text-white"
                  : "border border-white/10 text-brand-muted hover:text-brand-white"
              }`}
            >
              {i + 1}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

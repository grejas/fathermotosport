"use client";

import { useEffect, useState } from "react";
import { useLocale } from "next-intl";
import Image from "next/image";
import { Link } from "@/lib/i18n/navigation";
import { Newspaper } from "lucide-react";
import type { Post } from "@/lib/types";
import { getImageUrl, truncate } from "@/lib/utils";
import { translateText } from "@/lib/utils/translate";

export function PostCard({ post }: { post: Post }) {
  const locale = useLocale();
  const [translated, setTranslated] = useState<{ title: string; excerpt: string | null } | null>(null);

  // Mismo patrón que ProductClient.tsx: traduce silenciosamente title/excerpt
  // al idioma activo (pt/en); en 'es' no se traduce nada.
  useEffect(() => {
    if (locale === "es") {
      setTranslated(null);
      return;
    }
    let cancelled = false;
    (async () => {
      const [title, excerpt] = await Promise.all([
        translateText(post.title, locale),
        post.excerpt ? translateText(post.excerpt, locale) : Promise.resolve(post.excerpt),
      ]);
      if (!cancelled) setTranslated({ title, excerpt });
    })();
    return () => {
      cancelled = true;
    };
  }, [post, locale]);

  const displayTitle = translated?.title ?? post.title;
  const displayExcerpt = translated ? translated.excerpt : post.excerpt;

  return (
    <Link href={`/blog/${post.slug}`} className="group" aria-label={displayTitle}>
      <article className="card-product h-full hover:border-brand-red/40">
        <div className="relative aspect-[16/10] overflow-hidden bg-brand-dark">
          {post.cover_image ? (
            <Image
              src={getImageUrl(post.cover_image)}
              alt={displayTitle}
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
          <h3 className="line-clamp-2 min-h-[3.25rem] pb-0.5 text-sm font-semibold leading-snug text-brand-red sm:text-base">
            {displayTitle}
          </h3>

          {displayExcerpt && (
            <p className="mt-2 line-clamp-2 text-xs text-brand-muted sm:text-sm">{truncate(displayExcerpt, 140)}</p>
          )}
        </div>
      </article>
    </Link>
  );
}

"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { Bebas_Neue } from "next/font/google";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { ArrowRight, Newspaper } from "lucide-react";
import { usePosts } from "@/lib/hooks/usePosts";
import { getImageUrl, formatDate, truncate } from "@/lib/utils";
import { translateText } from "@/lib/utils/translate";
import type { Post } from "@/lib/types";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

function BlogSectionCard({ post }: { post: Post }) {
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
    <Link href={`/blog/${post.slug}`} className="group">
      <article className="card-product h-full hover:border-brand-red/40">
        <div className="relative aspect-[16/10] overflow-hidden bg-brand-dark">
          {post.cover_image ? (
            <Image
              src={getImageUrl(post.cover_image)}
              alt={displayTitle}
              fill
              sizes="(max-width: 768px) 100vw, 33vw"
              className="object-cover transition-transform duration-500 group-hover:scale-105"
            />
          ) : (
            <div className="flex h-full items-center justify-center">
              <Newspaper size={40} className="text-brand-red opacity-30" />
            </div>
          )}
        </div>

        <div className="p-5">
          <h3 className="line-clamp-2 min-h-[3.25rem] pb-0.5 text-base font-semibold leading-snug text-brand-red">
            {displayTitle}
          </h3>
          {displayExcerpt && (
            <p className="mt-2 line-clamp-2 text-sm text-brand-muted">{truncate(displayExcerpt, 100)}</p>
          )}
          {post.published_at && (
            <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-brand-gold">
              {formatDate(post.published_at)}
            </p>
          )}
        </div>
      </article>
    </Link>
  );
}

/**
 * Destaca los 3 posts publicados más recientes en la home. Si no hay ninguno
 * publicado, la sección no se renderiza (sin estado vacío ni placeholder).
 */
export function BlogSection() {
  const t = useTranslations("home");
  const { data, isLoading } = usePosts(1);
  const posts = (data?.data ?? []).slice(0, 3);

  if (isLoading || posts.length === 0) return null;

  return (
    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <div className="mb-8 text-center">
        <h2 className={`${bebasNeue.className} text-4xl uppercase tracking-wide text-brand-red sm:text-5xl`}>
          {t("blog_title")}
        </h2>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        {posts.map((post) => (
          <BlogSectionCard key={post.id} post={post} />
        ))}
      </div>

      <div className="mt-10 text-center">
        <Link href="/blog" className="btn-glass">
          {t("blog_view_all")} <ArrowRight size={16} />
        </Link>
      </div>
    </section>
  );
}

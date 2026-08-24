"use client";

import Image from "next/image";
import { Bebas_Neue } from "next/font/google";
import { useTranslations } from "next-intl";
import { Link } from "@/lib/i18n/navigation";
import { ArrowRight, Newspaper } from "lucide-react";
import { usePosts } from "@/lib/hooks/usePosts";
import { getImageUrl, formatDate, truncate } from "@/lib/utils";

const bebasNeue = Bebas_Neue({ weight: "400", subsets: ["latin"] });

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
        <h2 className={`${bebasNeue.className} text-4xl uppercase tracking-wide text-brand-white sm:text-5xl`}>
          {t("blog_title")}
        </h2>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        {posts.map((post) => (
          <Link key={post.id} href={`/blog/${post.slug}`} className="group">
            <article className="card-product h-full transition-colors duration-300 hover:border-brand-red/40">
              <div className="relative aspect-[16/10] overflow-hidden bg-brand-dark">
                {post.cover_image ? (
                  <Image
                    src={getImageUrl(post.cover_image)}
                    alt={post.title}
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
                <h3 className="line-clamp-2 min-h-[2.5rem] text-base font-semibold text-brand-white">
                  {post.title}
                </h3>
                {post.excerpt && (
                  <p className="mt-2 line-clamp-2 text-sm text-brand-muted">{truncate(post.excerpt, 100)}</p>
                )}
                {post.published_at && (
                  <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-brand-gold">
                    {formatDate(post.published_at)}
                  </p>
                )}
              </div>
            </article>
          </Link>
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

"use client";

import { useEffect, useState } from "react";
import { useLocale } from "next-intl";
import Image from "next/image";
import { ArrowLeft } from "lucide-react";
import type { Post } from "@/lib/types";
import { getImageUrl } from "@/lib/utils";
import { Link } from "@/lib/i18n/navigation";
import { ProductDescription } from "@/components/product/ProductDescription";
import { translateText } from "@/lib/utils/translate";

export function BlogPostClient({ post }: { post: Post }) {
  const locale = useLocale();
  const [translated, setTranslated] = useState<{
    title: string;
    excerpt: string | null;
    content: string;
  } | null>(null);

  // Traduce silenciosamente title/excerpt/content al idioma activo (pt/en)
  // usando la API pública de Google Translate. El contenido en la base de
  // datos vive en español; en 'es' no se traduce nada. Mismo patrón que
  // ProductClient.tsx para name/description.
  useEffect(() => {
    if (locale === "es") {
      setTranslated(null);
      return;
    }
    let cancelled = false;
    (async () => {
      const [title, excerpt, content] = await Promise.all([
        translateText(post.title, locale),
        post.excerpt ? translateText(post.excerpt, locale) : Promise.resolve(post.excerpt),
        translateText(post.content, locale),
      ]);
      if (!cancelled) setTranslated({ title, excerpt, content });
    })();
    return () => {
      cancelled = true;
    };
  }, [post, locale]);

  const displayPost = translated
    ? { ...post, title: translated.title, excerpt: translated.excerpt, content: translated.content }
    : post;

  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-16 sm:px-6 sm:pt-20 lg:pt-24">
      <Link
        href="/blog"
        className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-muted transition hover:text-brand-red"
      >
        <ArrowLeft size={16} /> Volver al blog
      </Link>

      <div className="space-y-6">
        {post.cover_image && (
          <div className="card-product hover:border-brand-red/40">
            <div className="relative aspect-[16/9] w-full overflow-hidden bg-brand-dark">
              <Image
                src={getImageUrl(post.cover_image)}
                alt={displayPost.title}
                fill
                priority
                sizes="(max-width: 768px) 100vw, 768px"
                className="object-cover"
              />
            </div>
          </div>
        )}

        <article className="card-product hover:border-brand-red/40 p-6 sm:p-8 lg:p-10">
          <h1 className="text-2xl font-bold leading-snug text-brand-white sm:text-3xl lg:text-4xl">
            {displayPost.title}
          </h1>

          <div className="mt-8">
            <ProductDescription html={displayPost.content} />
          </div>
        </article>
      </div>

      <div className="mt-8">
        <Link href="/blog" className="btn-glass">
          <ArrowLeft size={16} /> Volver al blog
        </Link>
      </div>
    </div>
  );
}

import { cache } from "react";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Image from "next/image";
import { ArrowLeft } from "lucide-react";
import { getPostBySlug } from "@/lib/api/posts";
import { getImageUrl, formatDate } from "@/lib/utils";
import { Link } from "@/lib/i18n/navigation";
import { ProductDescription } from "@/components/product/ProductDescription";

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

  return (
    <article className="mx-auto max-w-3xl px-4 pb-16 pt-16 sm:px-6 sm:pt-20 lg:pt-24">
      <Link
        href="/blog"
        className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-muted transition hover:text-brand-red"
      >
        <ArrowLeft size={16} /> Volver al blog
      </Link>

      {post.cover_image && (
        <div className="relative mb-8 aspect-[16/9] w-full overflow-hidden rounded-2xl border border-white/[0.06]">
          <Image
            src={getImageUrl(post.cover_image)}
            alt={post.title}
            fill
            priority
            sizes="(max-width: 768px) 100vw, 768px"
            className="object-cover"
          />
        </div>
      )}

      <h1 className="text-2xl font-bold text-brand-white sm:text-3xl lg:text-4xl">{post.title}</h1>

      <div className="mt-3 flex items-center gap-2 text-sm text-brand-muted">
        {post.author && <span>{post.author.name}</span>}
        {post.author && post.published_at && <span aria-hidden>·</span>}
        {post.published_at && <span>{formatDate(post.published_at)}</span>}
      </div>

      <div className="mt-8">
        <ProductDescription html={post.content} />
      </div>

      <div className="mt-12">
        <Link href="/blog" className="btn-glass">
          <ArrowLeft size={16} /> Volver al blog
        </Link>
      </div>
    </article>
  );
}

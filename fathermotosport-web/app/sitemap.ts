import type { MetadataRoute } from "next";
import { getAllPostsServidor, getAllProductsServidor, LOCALES, localeUrl } from "@/lib/seo";

/** Se regenera como mucho cada hora. */
export const revalidate = 3600;

const ESTATICAS: { path: string; priority: number; changeFrequency: MetadataRoute.Sitemap[number]["changeFrequency"] }[] = [
  { path: "/", priority: 1, changeFrequency: "daily" },
  { path: "/catalog", priority: 0.9, changeFrequency: "daily" },
  { path: "/black-friday", priority: 0.8, changeFrequency: "daily" },
  { path: "/blog", priority: 0.6, changeFrequency: "weekly" },
  { path: "/returns-policy", priority: 0.3, changeFrequency: "yearly" },
  { path: "/terms", priority: 0.2, changeFrequency: "yearly" },
  { path: "/privacy", priority: 0.2, changeFrequency: "yearly" },
];

/** Una entrada por idioma, con sus alternativas hreflang. */
function entrada(path: string, extra: Partial<MetadataRoute.Sitemap[number]> = {}): MetadataRoute.Sitemap {
  const languages = Object.fromEntries(LOCALES.map((l) => [l, localeUrl(path, l)]));
  return LOCALES.map((l) => ({ url: localeUrl(path, l), alternates: { languages }, ...extra }));
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [productos, posts] = await Promise.all([getAllProductsServidor(), getAllPostsServidor()]);

  return [
    ...ESTATICAS.flatMap((e) => entrada(e.path, { priority: e.priority, changeFrequency: e.changeFrequency })),
    ...productos
      .filter((p) => p.is_active !== false)
      .flatMap((p) => entrada(`/product/${p.slug}`, { priority: 0.7, changeFrequency: "weekly" })),
    ...posts.flatMap((p) =>
      entrada(`/blog/${p.slug}`, {
        priority: 0.5,
        changeFrequency: "monthly",
        ...(p.published_at ? { lastModified: new Date(p.published_at) } : {}),
      })
    ),
  ];
}

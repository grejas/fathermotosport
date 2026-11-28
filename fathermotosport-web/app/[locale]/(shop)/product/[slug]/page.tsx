import type { Metadata } from "next";
import { getShippingReturnsContent } from "@/lib/shippingReturns";
import { ProductClient } from "./ProductClient";
import { formatPrice, finalPrice } from "@/lib/utils";
import {
  alternatesFor,
  getProductServidor,
  jsonLdHtml,
  ogImage,
  ogLocale,
  plainText,
  productJsonLd,
  SITE_NAME,
} from "@/lib/seo";

type Params = { params: { locale: string; slug: string } };

/**
 * Título, descripción e imagen propios de cada producto: es lo que muestran Google y la
 * vista previa al compartir el link por WhatsApp, Instagram o Facebook.
 */
export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const product = await getProductServidor(params.slug);
  if (!product) return {};

  const precio = formatPrice(finalPrice(product));
  const titulo = product.name;
  const descripcion =
    plainText(product.short_description || product.description, 150) ||
    `${product.name}${product.brand?.name ? ` · ${product.brand.name}` : ""} · ${precio}`;
  const imagen = await ogImage(product.primary_image);
  const path = `/product/${product.slug}`;

  return {
    title: titulo,
    description: descripcion,
    alternates: alternatesFor(path, params.locale),
    openGraph: {
      type: "website",
      siteName: SITE_NAME,
      locale: ogLocale(params.locale),
      title: `${titulo} · ${precio}`,
      description: descripcion,
      url: alternatesFor(path, params.locale)?.canonical as string,
      ...(imagen ? { images: [{ url: imagen, alt: product.name }] } : {}),
    },
    twitter: {
      card: imagen ? "summary_large_image" : "summary",
      title: `${titulo} · ${precio}`,
      description: descripcion,
      ...(imagen ? { images: [imagen] } : {}),
    },
  };
}

export default async function ProductPage({ params }: Params) {
  // Se resuelve en el servidor para que el acordeón no dependa de un fetch del navegador.
  // El producto sale de la caché de datos (misma lectura que generateMetadata).
  const [{ summary }, product] = await Promise.all([
    getShippingReturnsContent(params.locale),
    getProductServidor(params.slug),
  ]);

  return (
    <>
      {product && (
        <script type="application/ld+json" dangerouslySetInnerHTML={jsonLdHtml(productJsonLd(product, params.locale))} />
      )}
      <ProductClient slug={params.slug} shippingReturns={summary} />
    </>
  );
}

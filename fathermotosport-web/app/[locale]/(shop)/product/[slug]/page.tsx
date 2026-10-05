import { getShippingReturnsContent } from "@/lib/shippingReturns";
import { ProductClient } from "./ProductClient";

export default async function ProductPage({
  params,
}: {
  params: { locale: string; slug: string };
}) {
  // Se resuelve en el servidor para que el acordeón no dependa de un fetch del navegador.
  const { summary } = await getShippingReturnsContent(params.locale);

  return <ProductClient slug={params.slug} shippingReturns={summary} />;
}

import { ProductClient } from "./ProductClient";

export default function ProductPage({ params }: { params: { slug: string } }) {
  return <ProductClient slug={params.slug} />;
}

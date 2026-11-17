import { useQuery } from "@tanstack/react-query";
import { getProducts } from "@/lib/api/products";
import type { Product } from "@/lib/types";

/** Todo el catálogo activo (≈137 productos, 3 páginas de 60). Máx. 10 páginas por seguridad. */
export async function fetchAllProducts(): Promise<Product[]> {
  const all: Product[] = [];
  let page = 1;
  let last = 1;
  do {
    const res = await getProducts({ per_page: 60, page });
    all.push(...res.data);
    last = res.meta?.last_page ?? 1;
    page += 1;
  } while (page <= last && page <= 10);
  return all;
}

/**
 * Catálogo completo en caché compartida (Black Friday, venta cruzada del carrito).
 * Una sola descarga por sesión de navegación cada 5 minutos.
 */
export function useAllProducts(enabled = true) {
  return useQuery({
    queryKey: ["all-products"],
    queryFn: fetchAllProducts,
    staleTime: 5 * 60_000,
    enabled,
  });
}

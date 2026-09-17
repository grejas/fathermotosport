import { useQuery } from "@tanstack/react-query";
import * as flashPromoApi from "@/lib/api/flashPromo";

/**
 * Lista de promociones flash vigentes AHORA. Se revalida periódicamente para que
 * aparezcan/desaparezcan solas al cambiar su ventana, sin recargar.
 */
export function useFlashPromos() {
  return useQuery({
    queryKey: ["flash-promo"],
    queryFn: flashPromoApi.getFlashPromos,
    // Revalida cada 15s mientras el usuario esté en la página.
    refetchInterval: 15_000,
    refetchOnWindowFocus: true,
    staleTime: 0,
  });
}

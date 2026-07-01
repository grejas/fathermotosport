import { useQuery } from "@tanstack/react-query";
import * as ordersApi from "@/lib/api/orders";

export function useMyOrders() {
  return useQuery({
    queryKey: ["orders", "mine"],
    queryFn: ordersApi.getMyOrders,
  });
}

export function useOrder(id: string) {
  return useQuery({
    queryKey: ["order", id],
    queryFn: () => ordersApi.getOrder(id),
    enabled: !!id,
  });
}

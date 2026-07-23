import { useQuery } from "@tanstack/react-query";
import * as visorColorsApi from "@/lib/api/visorColors";

export function useVisorColors(enabled: boolean = true) {
  return useQuery({
    queryKey: ["visor-colors"],
    queryFn: visorColorsApi.getVisorColors,
    enabled,
  });
}

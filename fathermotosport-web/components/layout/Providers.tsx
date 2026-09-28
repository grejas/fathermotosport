"use client";

import { useEffect, useState, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "react-hot-toast";
import { useAuthStore } from "@/store/authStore";

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { staleTime: 60_000, retry: 1, refetchOnWindowFocus: false },
        },
      })
  );

  // Refresca el usuario DESPUÉS de que Zustand rehidrate desde localStorage.
  // Antes se leía isAuth en el primer render, cuando todavía era false porque la
  // rehidratación ocurre después: /auth/me nunca se llamaba y el perfil quedaba vacío.
  useEffect(() => {
    const refrescarSiHaySesion = () => {
      const { isAuth, refreshUser } = useAuthStore.getState();
      if (isAuth) refreshUser();
    };

    // Si la rehidratación ya ocurrió, onFinishHydration no volverá a dispararse.
    if (useAuthStore.persist.hasHydrated()) {
      refrescarSiHaySesion();
    }

    return useAuthStore.persist.onFinishHydration(refrescarSiHaySesion);
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <Toaster
        position="bottom-right"
        toastOptions={{
          style: {
            background: "#141414",
            color: "#F5F5F5",
            border: "1px solid rgba(255,255,255,0.1)",
          },
          success: { iconTheme: { primary: "#E8001D", secondary: "#fff" } },
        }}
      />
    </QueryClientProvider>
  );
}

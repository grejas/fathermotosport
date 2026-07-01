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

  const isAuth = useAuthStore((s) => s.isAuth);
  const refreshUser = useAuthStore((s) => s.refreshUser);

  // Si hay sesión persistida, recupera el usuario al montar.
  useEffect(() => {
    if (isAuth) refreshUser();
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

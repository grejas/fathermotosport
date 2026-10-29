"use client";

import { Suspense, useEffect, useRef } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useAuthStore } from "@/store/authStore";
import type { User } from "@/lib/types";
import { updateLocale } from "@/lib/api/auth";
import { APP_LOCALES, GOOGLE_LOCALE_KEY, type AppLocale } from "@/lib/locale";

function GoogleCallbackInner() {
  const router = useRouter();
  const params = useSearchParams();
  const loginUser = useAuthStore((s) => s.loginUser);
  const refreshUser = useAuthStore((s) => s.refreshUser);
  const handled = useRef(false);

  useEffect(() => {
    if (handled.current) return;
    handled.current = true;

    const token = params.get("token");
    const userStr = params.get("user"); // useSearchParams ya viene decodificado

    if (!token) {
      router.replace("/login?error=google_auth_failed");
      return;
    }

    try {
      const user = userStr ? (JSON.parse(userStr) as User) : ({} as User);
      // Persiste el token + isAuth de inmediato...
      loginUser({ user, token });

      // Idioma con que salió a Google (este callback no lleva prefijo de idioma).
      let locale: string | null = null;
      try {
        locale = localStorage.getItem(GOOGLE_LOCALE_KEY);
        localStorage.removeItem(GOOGLE_LOCALE_KEY);
      } catch {
        /* sin almacenamiento */
      }
      const guardarIdioma = APP_LOCALES.includes(locale as AppLocale)
        ? updateLocale(locale as AppLocale).catch(() => undefined)
        : Promise.resolve();

      // ...y luego canonicaliza el usuario desde /auth/me (shape/rol correctos).
      const destino = locale && locale !== "es" ? `/${locale}/profile` : "/profile";
      guardarIdioma.then(() => refreshUser()).finally(() => router.replace(destino));
    } catch {
      router.replace("/login?error=google_auth_failed");
    }
  }, [params, router, loginUser, refreshUser]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-carbon">
      <div className="text-center">
        <div className="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-brand-red border-t-transparent" />
        <p className="text-white/60">Iniciando sesión con Google…</p>
      </div>
    </div>
  );
}

export default function GoogleCallbackPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-brand-carbon">
          <div className="h-12 w-12 animate-spin rounded-full border-4 border-brand-red border-t-transparent" />
        </div>
      }
    >
      <GoogleCallbackInner />
    </Suspense>
  );
}

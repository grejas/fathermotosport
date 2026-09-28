import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { User } from "@/lib/types";
import { TOKEN_KEY, setUnauthorizedHandler } from "@/lib/api/client";
import * as authApi from "@/lib/api/auth";

interface AuthState {
  user: User | null;
  token: string | null;
  isAuth: boolean;
  loginUser: (data: { user: User; token: string }) => void;
  registerUser: (data: { user: User; token: string }) => void;
  logoutUser: () => Promise<void>;
  refreshUser: () => Promise<void>;
  setUser: (user: User) => void;
}

function storeToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isAuth: false,

      loginUser: ({ user, token }) => {
        storeToken(token);
        set({ user, token, isAuth: true });
      },

      registerUser: ({ user, token }) => {
        storeToken(token);
        set({ user, token, isAuth: true });
      },

      logoutUser: async () => {
        try {
          await authApi.logout();
        } catch {
          /* ignorar errores de red al cerrar sesión */
        }
        storeToken(null);
        set({ user: null, token: null, isAuth: false });
      },

      refreshUser: async () => {
        try {
          const user = await authApi.getMe();
          set({ user, isAuth: true });
        } catch {
          storeToken(null);
          set({ user: null, token: null, isAuth: false });
        }
      },

      setUser: (user) => set({ user }),
    }),
    {
      name: "fms-auth",
      storage: createJSONStorage(() => localStorage),
      // Se persiste también el usuario: así al recargar los datos se ven al
      // instante y /auth/me solo los refresca en segundo plano. Antes quedaba
      // isAuth=true con user=null y la pantalla salía vacía.
      partialize: (state) => ({ token: state.token, isAuth: state.isAuth, user: state.user }),
    }
  )
);

// Un 401 de cualquier petición cierra la sesión en el estado de la app, no solo
// en localStorage, para que los layouts redirijan al login.
setUnauthorizedHandler(() => {
  useAuthStore.setState({ user: null, token: null, isAuth: false });
});

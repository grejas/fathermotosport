import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { User } from "@/lib/types";
import { TOKEN_KEY } from "@/lib/api/client";
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
      // Persistencia parcial: solo token e isAuth.
      partialize: (state) => ({ token: state.token, isAuth: state.isAuth }),
    }
  )
);

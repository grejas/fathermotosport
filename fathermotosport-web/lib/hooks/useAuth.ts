"use client";

import { useMutation } from "@tanstack/react-query";
import { useAuthStore } from "@/store/authStore";
import * as authApi from "@/lib/api/auth";
import type { LoginPayload, RegisterPayload } from "@/lib/api/auth";

/** Estado de autenticación reactivo. */
export function useAuth() {
  const user = useAuthStore((s) => s.user);
  const isAuth = useAuthStore((s) => s.isAuth);
  const token = useAuthStore((s) => s.token);
  return { user, isAuth, token };
}

export function useLogin() {
  const loginUser = useAuthStore((s) => s.loginUser);
  return useMutation({
    mutationFn: (payload: LoginPayload) => authApi.login(payload),
    onSuccess: (data) => loginUser({ user: data.user, token: data.token }),
  });
}

export function useRegister() {
  const registerUser = useAuthStore((s) => s.registerUser);
  return useMutation({
    mutationFn: (payload: RegisterPayload) => authApi.register(payload),
    onSuccess: (data) => registerUser({ user: data.user, token: data.token }),
  });
}

export function useLogout() {
  const logoutUser = useAuthStore((s) => s.logoutUser);
  return useMutation({ mutationFn: () => logoutUser() });
}

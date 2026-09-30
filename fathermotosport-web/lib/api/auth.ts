import apiClient from "./client";
import type { AuthResponse, User } from "@/lib/types";

export interface LoginPayload {
  email: string;
  password: string;
}

export interface SendVerificationCodePayload {
  email: string;
  recaptcha_token: string;
}

export interface VerifyAndRegisterPayload {
  email: string;
  code: string;
  first_name: string;
  last_name: string;
  phone?: string;
  birth_date?: string;
  country: string;
  password: string;
  password_confirmation: string;
}

/**
 * Crea una cuenta a partir de un pedido de invitado ya pagado. El nombre y el email
 * salen del pedido; acá solo va la contraseña. Autoriza el token del pedido.
 */
export async function registerFromOrder(payload: {
  orderId: string;
  accessToken: string;
  password: string;
  passwordConfirmation: string;
}): Promise<AuthResponse & { welcome_coupon?: { code: string; value: string }; linked_orders?: number }> {
  const { data } = await apiClient.post(
    "/auth/register-from-order",
    {
      order_id: payload.orderId,
      password: payload.password,
      password_confirmation: payload.passwordConfirmation,
    },
    { headers: { "X-Order-Token": payload.accessToken } }
  );
  return data;
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await apiClient.post<AuthResponse>("/auth/login", payload);
  return data;
}

export async function sendVerificationCode(
  payload: SendVerificationCodePayload
): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(
    "/auth/send-verification-code",
    payload
  );
  return data;
}

export async function verifyAndRegister(payload: VerifyAndRegisterPayload): Promise<AuthResponse> {
  const { data } = await apiClient.post<AuthResponse>("/auth/verify-and-register", payload);
  return data;
}

export async function logout(): Promise<void> {
  await apiClient.post("/auth/logout");
}

export async function getMe(): Promise<User> {
  const { data } = await apiClient.get<{ data: User }>("/auth/me");
  return data.data;
}

export async function forgotPassword(email: string): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>("/auth/forgot-password", { email });
  return data;
}

export interface ResetPasswordPayload {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>("/auth/reset-password", payload);
  return data;
}

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
  password: string;
  password_confirmation: string;
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

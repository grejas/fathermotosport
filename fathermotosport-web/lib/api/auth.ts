import apiClient from "./client";
import type { AuthResponse, User } from "@/lib/types";

export interface LoginPayload {
  email: string;
  password: string;
  /** Idioma de la web: queda como idioma del cliente. */
  locale?: string;
}

/** Teléfono (+59176543210), país ISO, fecha opcional y, sin fecha, la casilla de 18 años. */
export interface PersonalDataPayload {
  phone: string;
  country: string;
  birth_date?: string;
  adult_confirmed?: boolean;
}

/** El paso 1 manda también los datos personales, para avisar antes de enviar el código. */
export interface SendVerificationCodePayload extends Partial<PersonalDataPayload> {
  email: string;
  recaptcha_token: string;
}

export interface VerifyAndRegisterPayload extends PersonalDataPayload {
  email: string;
  code: string;
  first_name: string;
  last_name: string;
  locale?: string;
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

/** Ventana "Completa tus datos" (clientes antiguos o de Google). */
export async function completeProfile(payload: PersonalDataPayload): Promise<{ message: string; user: User }> {
  const { data } = await apiClient.post<{ message: string; user: User }>("/user/complete-profile", payload);
  return data;
}

/** Cerró la ventana sin completar: no se vuelve a mostrar. */
export async function dismissProfilePrompt(): Promise<{ user: User }> {
  const { data } = await apiClient.post<{ user: User }>("/user/profile-prompt/dismiss");
  return data;
}

export async function updateLocale(locale: string): Promise<void> {
  await apiClient.put("/user/locale", { locale });
}

/** Código de 6 dígitos al correo de la cuenta, para verificarlo. */
export async function sendEmailCode(): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>("/user/email/send-code");
  return data;
}

export async function verifyEmailCode(code: string): Promise<{ message: string; user: User }> {
  const { data } = await apiClient.post<{ message: string; user: User }>("/user/email/verify", { code });
  return data;
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

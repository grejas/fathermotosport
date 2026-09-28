import axios, { type AxiosInstance } from "axios";

export const TOKEN_KEY = "fms_token";
export const SESSION_KEY = "fms_session";

const apiClient: AxiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? "https://api.fathermotosport.com/api/v1",
  headers: { Accept: "application/json", "Content-Type": "application/json" },
});

// Request: adjunta el Bearer token y el X-Session-Id desde localStorage.
apiClient.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;

    const session = localStorage.getItem(SESSION_KEY);
    if (session) config.headers["X-Session-Id"] = session;
  }
  return config;
});

/**
 * Qué hacer cuando la API responde 401. Lo registra el store de auth para cerrar
 * la sesión también en el estado de la app: si solo se borrara el token, la UI
 * seguiría creyendo que hay sesión y el cliente quedaría en una pantalla vacía.
 * Se hace con un callback y no importando el store para no crear un ciclo de imports.
 */
let onUnauthorized: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler;
}

// Response: ante un 401, limpia el token almacenado y avisa al store.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && typeof window !== "undefined") {
      localStorage.removeItem(TOKEN_KEY);
      onUnauthorized?.();
    }
    return Promise.reject(error);
  }
);

export default apiClient;

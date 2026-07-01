import axios, { type AxiosInstance } from "axios";

export const TOKEN_KEY = "fms_token";
export const SESSION_KEY = "fms_session";

const apiClient: AxiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1",
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

// Response: ante un 401, limpia el token almacenado.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && typeof window !== "undefined") {
      localStorage.removeItem(TOKEN_KEY);
    }
    return Promise.reject(error);
  }
);

export default apiClient;

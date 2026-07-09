/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  images: {
    formats: ["image/webp", "image/avif"],
    remotePatterns: [
      // Desarrollo local: imágenes servidas desde el storage de Laravel (:8000)
      {
        protocol: "http",
        hostname: "localhost",
        port: "8000",
        pathname: "/storage/**",
      },
      {
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8000",
        pathname: "/storage/**",
      },
      // Producción: API Laravel en Hostinger (storage local vía storage:link)
      {
        protocol: "https",
        hostname: "api.fathermotosport.com",
        pathname: "/storage/**",
      },
      // Producción: Cloudflare R2 / dominio de storage
      {
        protocol: "https",
        hostname: "storage.fathermotosport.com",
        pathname: "/**",
      },
      {
        protocol: "https",
        hostname: "**.r2.cloudflarestorage.com",
        pathname: "/**",
      },
    ],
  },
};

export default nextConfig;

import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./i18n.ts");

/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  images: {
    // El optimizador de Vercel devolvía 402 (OPTIMIZED_IMAGE_REQUEST_PAYMENT_REQUIRED)
    // con 208K imágenes sobre una cuota de 100K, y ninguna imagen del sitio cargaba.
    // Ahora se optimiza en origen: el loader pide a la API una versión WebP al ancho
    // justo (/storage/v/{ancho}/...), que se genera una vez y queda como archivo
    // estático. Sin cuota y sin pagar por visita.
    loader: "custom",
    loaderFile: "./lib/imageLoader.ts",
    // `formats` y `remotePatterns` no aplican con loader propio: se conservan por si
    // algún día se vuelve al optimizador de Next.
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

export default withNextIntl(nextConfig);

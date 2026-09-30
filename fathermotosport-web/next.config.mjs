import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./i18n.ts");

/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  images: {
    // El optimizador de Vercel devolvía 402 (OPTIMIZED_IMAGE_REQUEST_PAYMENT_REQUIRED)
    // con 208K imágenes sobre una cuota de 100K, y ninguna imagen del sitio cargaba.
    // Sin optimizador cada <Image> apunta a su URL original (api.fathermotosport.com),
    // que sirve los archivos sin límite de cuota.
    //
    // A cambio se pierde el redimensionado: el navegador descarga el archivo original
    // (200-700 KB por imagen del catálogo) aunque lo muestre en una ficha chica. Si eso
    // pesa en la carga, la salida es comprimir en origen al subir, no volver a pagar
    // por optimizar en cada visita.
    unoptimized: true,
    // `formats` y `remotePatterns` quedan inertes mientras unoptimized siga en true:
    // se conservan para no tener que reconstruirlos si algún día se reactiva.
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

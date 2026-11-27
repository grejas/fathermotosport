import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/seo";

export default function robots(): MetadataRoute.Robots {
  // Carrito, pago, cuenta y acceso no aportan nada a Google (y en los idiomas /pt y /en igual).
  const privadas = ["/checkout", "/cart", "/orders", "/profile", "/favorites", "/login", "/register", "/auth", "/forgot-password", "/reset-password", "/baja", "/api/"];
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: [...privadas, ...privadas.flatMap((p) => [`/pt${p}`, `/en${p}`])] }],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}

import type { Metadata } from "next";
import { Inter } from "next/font/google";
import Script from "next/script";
import "@/styles/globals.css";
import { Providers } from "@/components/layout/Providers";
import { Navbar } from "@/components/layout/Navbar";
import { Footer } from "@/components/layout/Footer";
import { CartDrawer } from "@/components/cart/CartDrawer";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" });

export const metadata: Metadata = {
  title: {
    default: "FatherMotoSport — Cascos y Accesorios Premium",
    template: "%s | FatherMotoSport",
  },
  description:
    "Cascos, guantes, botas, chamarras y repuestos premium. Envío gratis. AGV, Shoei, Shark, Alpinestars y más.",
  icons: {
    icon: "/icon.png",
    shortcut: "/favicon.ico",
  },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es" className={inter.variable}>
      <head>
        <Script id="google-translate-init" strategy="beforeInteractive">
          {`
            function googleTranslateElementInit() {
              new window.google.translate.TranslateElement(
                { pageLanguage: "es", includedLanguages: "es,pt,en", autoDisplay: false },
                "google_translate_element"
              );
            }
          `}
        </Script>
        <Script
          id="google-translate-script"
          src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"
          strategy="beforeInteractive"
        />
      </head>
      <body suppressHydrationWarning className="min-h-screen bg-brand-carbon font-sans text-brand-white antialiased">
        <div id="google_translate_element" className="hidden" />
        <Providers>
          <Navbar />
          <main className="min-h-screen">{children}</main>
          <Footer />
          <CartDrawer />
        </Providers>
      </body>
    </html>
  );
}

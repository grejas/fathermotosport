import type { Metadata } from "next";
import { Inter } from "next/font/google";
import { notFound } from "next/navigation";
import { NextIntlClientProvider } from "next-intl";
import { getMessages, setRequestLocale } from "next-intl/server";
import "@/styles/globals.css";
import "flag-icons/css/flag-icons.min.css";
import { Providers } from "@/components/layout/Providers";
import { Navbar } from "@/components/layout/Navbar";
import { Footer } from "@/components/layout/Footer";
import { CartDrawer } from "@/components/cart/CartDrawer";
import { getMenuCategories } from "@/lib/api/categories";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" });

const locales = ["es", "pt", "en"];

export function generateStaticParams() {
  return locales.map((locale) => ({ locale }));
}

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

export default async function RootLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: { locale: string };
}) {
  const { locale } = params;
  if (!locales.includes(locale)) notFound();

  setRequestLocale(locale);
  // Una lectura cacheada (tag "categories") para el navbar y el footer de todas las páginas.
  const [messages, categories] = await Promise.all([getMessages(), getMenuCategories()]);

  return (
    <html lang={locale} className={inter.variable}>
      <body suppressHydrationWarning className="min-h-screen bg-brand-carbon font-sans text-brand-white antialiased">
        <NextIntlClientProvider messages={messages}>
          <Providers>
            <Navbar categories={categories} />
            <main className="min-h-screen">{children}</main>
            <Footer categories={categories} />
            <CartDrawer />
          </Providers>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}

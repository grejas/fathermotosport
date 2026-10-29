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
import { ProfileCompletionModal } from "@/components/account/ProfileCompletionModal";
import { StoreSettingsProvider } from "@/components/layout/StoreSettingsProvider";
import { getMenuCategories } from "@/lib/api/categories";
import { getStoreSettings } from "@/lib/api/storeSettings";

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
  // Lecturas cacheadas (tags "categories" y "store-settings") para el navbar, el footer y
  // los componentes de cliente que muestran el contacto.
  const [messages, categories, storeSettings] = await Promise.all([
    getMessages(),
    getMenuCategories(),
    getStoreSettings(),
  ]);

  return (
    <html lang={locale} className={inter.variable}>
      <body suppressHydrationWarning className="min-h-screen bg-brand-carbon font-sans text-brand-white antialiased">
        <NextIntlClientProvider messages={messages}>
          <Providers>
            <StoreSettingsProvider value={storeSettings}>
              <Navbar categories={categories} />
              <main className="min-h-screen">{children}</main>
              <Footer categories={categories} settings={storeSettings} />
              <CartDrawer />
              {/* Una vez tras iniciar sesión, si faltan teléfono, país o verificar el correo. */}
              <ProfileCompletionModal />
            </StoreSettingsProvider>
          </Providers>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}

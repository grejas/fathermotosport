import { HeroSection } from "@/components/home/HeroSection";
import { FlashPromoBanner } from "@/components/home/FlashPromoBanner";
import { MarqueeBar } from "@/components/home/MarqueeBar";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { HomeAfterCategories } from "@/components/home/HomeAfterCategories";
import { PaymentBanner } from "@/components/home/PaymentBanner";
import { FooterStrip } from "@/components/home/FooterStrip";
import { ReviewsSection } from "@/components/home/ReviewsSection";
import { BlogSection } from "@/components/home/BlogSection";
import { FooterBanner } from "@/components/layout/FooterBanner";
import { getBannersServidor } from "@/lib/api/banners";

export default async function HomePage() {
  // En el servidor (con caché de 10 minutos y revalidación al guardar en el panel):
  // así el poster del hero llega en el HTML y se precarga como lo primero que se pinta.
  const [hero, despuesDeCategorias] = await Promise.all([
    getBannersServidor("hero"),
    getBannersServidor("home_after_categories"),
  ]);

  return (
    <>
      <FlashPromoBanner />
      <HeroSection banner={hero[0] ?? null} />
      <MarqueeBar />
      <CategoryGrid />
      <HomeAfterCategories banners={despuesDeCategorias} />
      <PaymentBanner />
      <FooterStrip />
      <ReviewsSection />
      <BlogSection />
      <FooterBanner />
    </>
  );
}

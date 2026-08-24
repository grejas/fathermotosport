import { HeroSection } from "@/components/home/HeroSection";
import { MarqueeBar } from "@/components/home/MarqueeBar";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { FeaturedProducts } from "@/components/home/FeaturedProducts";
import { PaymentBanner } from "@/components/home/PaymentBanner";
import { FooterStrip } from "@/components/home/FooterStrip";
import { BlogSection } from "@/components/home/BlogSection";
import { FooterBanner } from "@/components/layout/FooterBanner";

export default function HomePage() {
  return (
    <>
      <HeroSection />
      <MarqueeBar />
      <CategoryGrid />
      <FeaturedProducts />
      <PaymentBanner />
      <FooterStrip />
      <BlogSection />
      <FooterBanner />
    </>
  );
}

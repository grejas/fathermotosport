import { HeroSection } from "@/components/home/HeroSection";
import { FlashPromoBanner } from "@/components/home/FlashPromoBanner";
import { MarqueeBar } from "@/components/home/MarqueeBar";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { PaymentBanner } from "@/components/home/PaymentBanner";
import { FooterStrip } from "@/components/home/FooterStrip";
import { ReviewsSection } from "@/components/home/ReviewsSection";
import { BlogSection } from "@/components/home/BlogSection";
import { FooterBanner } from "@/components/layout/FooterBanner";

export default function HomePage() {
  return (
    <>
      <FlashPromoBanner />
      <HeroSection />
      <MarqueeBar />
      <CategoryGrid />
      <PaymentBanner />
      <FooterStrip />
      <ReviewsSection />
      <BlogSection />
      <FooterBanner />
    </>
  );
}

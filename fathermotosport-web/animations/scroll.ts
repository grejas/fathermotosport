import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

if (typeof window !== "undefined") {
  gsap.registerPlugin(ScrollTrigger);
}

/** Anima la entrada del hero (texto + stats) con un timeline GSAP. */
export function initHeroScroll(root: HTMLElement | null): () => void {
  if (!root) return () => {};

  const ctx = gsap.context(() => {
    gsap.from("[data-hero-tag]", { y: 20, opacity: 0, duration: 0.6, ease: "power2.out" });
    gsap.from("[data-hero-title]", {
      y: 40,
      opacity: 0,
      duration: 0.9,
      delay: 0.1,
      ease: "power3.out",
    });
    gsap.from("[data-hero-sub]", { y: 20, opacity: 0, duration: 0.7, delay: 0.3, ease: "power2.out" });
    gsap.from("[data-hero-cta]", {
      y: 20,
      opacity: 0,
      duration: 0.6,
      delay: 0.45,
      stagger: 0.1,
      ease: "power2.out",
    });
    gsap.from("[data-hero-stat]", {
      y: 16,
      opacity: 0,
      duration: 0.6,
      delay: 0.6,
      stagger: 0.12,
      ease: "power2.out",
    });
  }, root);

  return () => ctx.revert();
}

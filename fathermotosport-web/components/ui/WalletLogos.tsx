import { faAmazonPay, faApplePay, faGooglePay } from "@fortawesome/free-brands-svg-icons";
import type { IconDefinition } from "@fortawesome/free-brands-svg-icons";
import { cn } from "@/lib/utils";

/**
 * Marcas de las billeteras que cobra Stripe (Apple Pay, Google Pay, Amazon Pay).
 * Los trazos vienen del paquete de marcas de Font Awesome (CC BY 4.0): no se dibujan a
 * mano, y solo se importan estos tres iconos, así que no suma peso al bundle.
 */
const WALLETS: { id: "apple_pay" | "google_pay" | "amazon_pay"; name: string; icon: IconDefinition }[] = [
  { id: "apple_pay", name: "Apple Pay", icon: faApplePay },
  { id: "google_pay", name: "Google Pay", icon: faGooglePay },
  { id: "amazon_pay", name: "Amazon Pay", icon: faAmazonPay },
];

function BrandSvg({ icon, title, className }: { icon: IconDefinition; title: string; className?: string }) {
  const [width, height, , , path] = icon.icon;
  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      role="img"
      aria-label={title}
      fill="currentColor"
      className={className}
    >
      <title>{title}</title>
      {Array.isArray(path) ? path.map((d) => <path key={d} d={d} />) : <path d={path} />}
    </svg>
  );
}

interface Props {
  /** "chip": tarjetitas blancas (footer). "inline": solo los logos, para dentro de una opción. */
  variant?: "chip" | "inline";
  className?: string;
}

export function WalletLogos({ variant = "chip", className }: Props) {
  if (variant === "inline") {
    return (
      <span className={cn("flex items-center gap-2 text-brand-white/80", className)} translate="no">
        {WALLETS.map((w) => (
          <BrandSvg key={w.id} icon={w.icon} title={w.name} className="h-6 w-auto" />
        ))}
      </span>
    );
  }

  return (
    <ul className={cn("flex flex-wrap gap-2", className)} translate="no">
      {WALLETS.map((w) => (
        <li
          key={w.id}
          className="flex h-8 items-center justify-center rounded-md bg-white px-2.5 text-[#111]"
          title={w.name}
        >
          <BrandSvg icon={w.icon} title={w.name} className="h-7 w-auto" />
        </li>
      ))}
    </ul>
  );
}

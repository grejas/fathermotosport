import { faAmazonPay, faApplePay, faGooglePay } from "@fortawesome/free-brands-svg-icons";
import type { IconDefinition } from "@fortawesome/free-brands-svg-icons";
import {
  siAmericanexpress,
  siBinance,
  siBitcoin,
  siMastercard,
  siMercadopago,
  siPaypal,
  siTether,
  siVisa,
} from "simple-icons";
import type { SimpleIcon } from "simple-icons";
import { cn } from "@/lib/utils";
import { isGatewayAdvertised } from "@/lib/data/paymentMethods";

/**
 * Marcas de los medios de pago. No se dibujan a mano: los trazos vienen de paquetes
 * de marcas (Font Awesome Brands, CC BY 4.0, para las billeteras; Simple Icons, CC0,
 * para el resto) y solo se importan los iconos usados, así que el bundle no crece.
 */

type Marca = {
  id: string;
  name: string;
  /** Trazo de Font Awesome (viewBox propio) o de Simple Icons (24×24). */
  fa?: IconDefinition;
  si?: SimpleIcon;
  /** Texto junto al símbolo cuando el logo solo no se reconoce (p. ej. la "P" de PayPal). */
  label?: string;
  /** Logo que ya es un logotipo ancho: se dibuja más grande y sin texto. */
  wide?: boolean;
};

const BILLETERAS: Marca[] = [
  { id: "apple_pay", name: "Apple Pay", fa: faApplePay },
  { id: "google_pay", name: "Google Pay", fa: faGooglePay },
  { id: "amazon_pay", name: "Amazon Pay", fa: faAmazonPay },
];

const RESTO: Marca[] = [
  { id: "paypal", name: "PayPal", si: siPaypal, label: "PayPal" },
  { id: "visa", name: "Visa", si: siVisa, wide: true },
  { id: "mastercard", name: "Mastercard", si: siMastercard, label: "Mastercard" },
  { id: "amex", name: "American Express", si: siAmericanexpress, wide: true },
  { id: "mercadopago", name: "Mercado Pago", si: siMercadopago, label: "Mercado Pago" },
  { id: "usdt", name: "USDT (Tether)", si: siTether, label: "USDT" },
  { id: "bitcoin", name: "Bitcoin", si: siBitcoin, label: "Bitcoin" },
  { id: "binance_pay", name: "Binance Pay", si: siBinance, label: "Binance Pay" },
];

/** PayPal y MercadoPago solo se muestran si el checkout los tiene encendidos. */
function seAnuncia(m: Marca): boolean {
  if (m.id === "paypal" || m.id === "mercadopago") return isGatewayAdvertised(m.id);
  return true;
}

function Simbolo({ marca, className }: { marca: Marca; className?: string }) {
  if (marca.fa) {
    const [width, height, , , path] = marca.fa.icon;
    return (
      <svg viewBox={`0 0 ${width} ${height}`} aria-hidden="true" fill="currentColor" className={className}>
        {Array.isArray(path) ? path.map((d) => <path key={d} d={d} />) : <path d={path} />}
      </svg>
    );
  }
  if (marca.si) {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true" fill={`#${marca.si.hex}`} className={className}>
        <path d={marca.si.path} />
      </svg>
    );
  }
  return null;
}

/** Tarjetita blanca con el logo a color, como las que se ven en las tiendas grandes. */
function Chip({ marca }: { marca: Marca }) {
  return (
    <li
      title={marca.name}
      aria-label={marca.name}
      className="flex h-8 items-center gap-1.5 rounded-md bg-white px-2.5 text-[#1A1A1A] shadow-sm ring-1 ring-black/5"
    >
      <Simbolo
        marca={marca}
        className={marca.fa ? "h-7 w-auto" : marca.wide ? "h-8 w-8" : "h-[18px] w-[18px] shrink-0"}
      />
      {marca.label && <span className="whitespace-nowrap text-[11px] font-semibold tracking-tight">{marca.label}</span>}
    </li>
  );
}

/** Todos los medios de pago (footer). */
export function PaymentLogos({ className }: { className?: string }) {
  return (
    <ul className={cn("flex flex-wrap gap-2", className)} translate="no" aria-label="Medios de pago">
      {[...BILLETERAS, ...RESTO.filter(seAnuncia)].map((m) => (
        <Chip key={m.id} marca={m} />
      ))}
    </ul>
  );
}

interface Props {
  /** "chip": tarjetitas blancas. "inline": solo los logos, para dentro de una opción. */
  variant?: "chip" | "inline";
  className?: string;
}

/** Logos de tarjeta (Visa, Mastercard, Amex), para la opción de tarjeta del checkout. */
export function CardLogos({ className }: { className?: string }) {
  return (
    <ul className={cn("flex flex-wrap gap-1.5", className)} translate="no" aria-label="Visa, Mastercard, American Express">
      {RESTO.filter((m) => ["visa", "mastercard", "amex"].includes(m.id)).map((m) => (
        <Chip key={m.id} marca={m} />
      ))}
    </ul>
  );
}

/** Solo las billeteras de Stripe (opción "Tarjeta o billetera digital" del checkout). */
export function WalletLogos({ variant = "chip", className }: Props) {
  if (variant === "inline") {
    return (
      <span className={cn("flex items-center gap-2 text-brand-white/80", className)} translate="no">
        {BILLETERAS.map((w) => (
          <span key={w.id} role="img" aria-label={w.name} title={w.name}>
            <Simbolo marca={w} className="h-6 w-auto" />
          </span>
        ))}
      </span>
    );
  }

  return (
    <ul className={cn("flex flex-wrap gap-2", className)} translate="no">
      {BILLETERAS.map((m) => (
        <Chip key={m.id} marca={m} />
      ))}
    </ul>
  );
}

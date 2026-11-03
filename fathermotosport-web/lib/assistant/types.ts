export type AssistantLocale = "es" | "pt" | "en";

export interface ChatMessage {
  role: "user" | "assistant";
  content: string;
}

/** Tarjeta de producto que el widget pinta bajo la respuesta. Todo sale de la API real. */
export interface ProductCard {
  name: string;
  slug: string;
  brand: string | null;
  /** Precio que paga el cliente (USD). */
  price: number;
  /** Precio de lista, solo si hay descuento vigente. */
  listPrice: number | null;
  discountPercent: number;
  inStock: boolean;
  /** Tallas con stock disponible. */
  sizes: string[];
  image: string | null;
}

export interface AssistantReply {
  reply: string;
  products: ProductCard[];
  /** "ai" = Claude con herramientas; "guided" = modo sin IA (respaldo). */
  mode: "ai" | "guided";
}

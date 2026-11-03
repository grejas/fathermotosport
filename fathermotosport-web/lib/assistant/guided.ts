import { normalize, searchProducts, shippingOptions, storeInfo } from "./tools";
import type { AssistantLocale, AssistantReply, ChatMessage } from "./types";

/**
 * Modo guiado (sin IA): reconoce el tema por palabras clave y responde solo con datos reales
 * de la tienda. Se usa cuando no hay ANTHROPIC_API_KEY o cuando la IA falla.
 */

const COUNTRIES: Record<string, string> = {
  bolivia: "BO", mexico: "MX", brasil: "BR", brazil: "BR", colombia: "CO", peru: "PE", chile: "CL", argentina: "AR",
  ecuador: "EC", "estados unidos": "US", eeuu: "US", usa: "US", "united states": "US", espana: "ES", spain: "ES",
  "costa rica": "CR", guatemala: "GT", "el salvador": "SV", uruguay: "UY", paraguay: "PY", panama: "PA",
};

function detectCountry(text: string): string | null {
  const n = ` ${normalize(text)} `;
  for (const [name, code] of Object.entries(COUNTRIES)) if (n.includes(` ${name} `)) return code;
  return null;
}

const T = {
  es: {
    welcome: "¡Hola! Soy el asistente de FatherMotoSport. Puedo ayudarte a encontrar productos, ver tallas y stock, costo de envío, pagos y devoluciones. ¿Qué buscas?",
    notFound: "No encontré ese producto en el catálogo. Prueba con otra palabra (por ejemplo la marca o el tipo) o continúa con un asesor por WhatsApp.",
    found: "Esto es lo que encontré. Precio, tallas y stock son los actuales de la tienda:",
    shippingAsk: "El envío depende del país y del peso del producto. Dime qué producto te interesa y a qué país lo enviamos, y te muestro el costo real.",
    shippingNone: "No encuentro tarifas de envío para ese destino; un asesor puede coordinarlo por WhatsApp.",
    shippingHead: (country: string, name: string) => `Envío de “${name}” a ${country}:`,
    free: "gratis",
    days: (a: number | null, b: number | null) => (a && b ? ` · ${a}–${b} días` : ""),
    pay: (list: string) => `Puedes pagar con ${list}. Otros métodos se coordinan con un asesor por WhatsApp.`,
    returnsNone: "La política de envíos y devoluciones está en la página “Política de Envío y Devoluciones”; un asesor te la explica por WhatsApp si lo prefieres.",
    sizes: "Cada producto tiene su guía de tallas en la página del producto. Dime cuál te interesa y te muestro las tallas con stock.",
    human: "Con gusto: continúa con un asesor por WhatsApp y llevará el resumen de esta conversación.",
  },
  pt: {
    welcome: "Olá! Sou o assistente da FatherMotoSport. Posso ajudar a encontrar produtos, ver tamanhos e estoque, frete, pagamentos e devoluções. O que você procura?",
    notFound: "Não encontrei esse produto no catálogo. Tente outra palavra (marca ou tipo) ou continue com um atendente pelo WhatsApp.",
    found: "Aqui está o que encontrei. Preço, tamanhos e estoque são os atuais da loja:",
    shippingAsk: "O frete depende do país e do peso do produto. Diga qual produto interessa e para qual país enviamos, e mostro o custo real.",
    shippingNone: "Não encontro tarifas de frete para esse destino; um atendente pode combinar pelo WhatsApp.",
    shippingHead: (country: string, name: string) => `Frete de “${name}” para ${country}:`,
    free: "grátis",
    days: (a: number | null, b: number | null) => (a && b ? ` · ${a}–${b} dias` : ""),
    pay: (list: string) => `Você pode pagar com ${list}. Outros métodos são combinados com um atendente pelo WhatsApp.`,
    returnsNone: "A política de envio e devoluções está na página “Política de Envio e Devoluções”; um atendente explica pelo WhatsApp se preferir.",
    sizes: "Cada produto tem seu guia de tamanhos na página do produto. Diga qual interessa e mostro os tamanhos em estoque.",
    human: "Com prazer: continue com um atendente pelo WhatsApp, levando o resumo desta conversa.",
  },
  en: {
    welcome: "Hi! I'm the FatherMotoSport assistant. I can help you find products, check sizes and stock, shipping cost, payments and returns. What are you looking for?",
    notFound: "I couldn't find that in the catalog. Try another word (brand or type) or continue with an advisor on WhatsApp.",
    found: "Here's what I found. Price, sizes and stock are the store's current ones:",
    shippingAsk: "Shipping depends on the country and the product's weight. Tell me which product and which country and I'll show the real cost.",
    shippingNone: "I can't find shipping rates for that destination; an advisor can arrange it on WhatsApp.",
    shippingHead: (country: string, name: string) => `Shipping “${name}” to ${country}:`,
    free: "free",
    days: (a: number | null, b: number | null) => (a && b ? ` · ${a}–${b} days` : ""),
    pay: (list: string) => `You can pay with ${list}. Other methods are arranged with an advisor on WhatsApp.`,
    returnsNone: "Our shipping and returns policy is on the “Shipping & Returns Policy” page; an advisor can walk you through it on WhatsApp.",
    sizes: "Every product has its size guide on the product page. Tell me which one and I'll show the sizes in stock.",
    human: "Happy to: continue with an advisor on WhatsApp, with a summary of this chat.",
  },
} as const;

type Intent = "greeting" | "shipping" | "payment" | "returns" | "sizes" | "human" | "search";

function intentOf(text: string): Intent {
  const n = normalize(text);
  if (!n || /^(hola|ola|hello|hi|buenas|buenos dias|buenas tardes|buenas noches|oi|bom dia|boa tarde)$/.test(n)) return "greeting";
  if (/(asesor|humano|persona|vendedor|whatsapp|atendente|advisor|agent|human)/.test(n)) return "human";
  if (/(envio|enviar|envian|entrega|llegar|llega|frete|enviam|shipping|ship|deliver)/.test(n)) return "shipping";
  if (/(pago|pagar|tarjeta|paypal|metodo|cuotas|pagamento|cartao|payment|card|pay )/.test(n)) return "payment";
  if (/(devol|garantia|cambio|reembolso|devolucao|troca|return|refund|warranty|exchange)/.test(n)) return "returns";
  if (/(guia de talla|que talla|mi talla|tamanho|medidas|size guide|what size)/.test(n)) return "sizes";
  return "search";
}

export async function respondGuided(messages: ChatMessage[], locale: AssistantLocale): Promise<AssistantReply> {
  const t = T[locale];
  const last = messages[messages.length - 1]?.content ?? "";
  const intent = intentOf(last);
  const history = messages.filter((m) => m.role === "user").map((m) => m.content).join(" ");

  if (intent === "greeting") return { reply: t.welcome, products: [], mode: "guided" };
  if (intent === "human") return { reply: t.human, products: [], mode: "guided" };
  if (intent === "sizes") return { reply: t.sizes, products: [], mode: "guided" };

  if (intent === "payment") {
    const info = await storeInfo(locale);
    return { reply: t.pay(info.paymentMethods.join(", ")), products: [], mode: "guided" };
  }

  if (intent === "returns") {
    const info = await storeInfo(locale);
    return { reply: info.returnsSummary ?? t.returnsNone, products: [], mode: "guided" };
  }

  if (intent === "shipping") {
    const country = detectCountry(last) ?? detectCountry(history);
    // Producto: de este mensaje o de lo conversado antes.
    const found = (await searchProducts(last, { limit: 3 })).length
      ? await searchProducts(last, { limit: 3 })
      : await searchProducts(history, { limit: 3 });
    if (!country || found.length === 0) return { reply: t.shippingAsk, products: found.slice(0, 1), mode: "guided" };

    const top = found[0];
    const quote = await shippingOptions(country, top.slug);
    if (!quote.available || quote.options.length === 0) return { reply: t.shippingNone, products: [top], mode: "guided" };
    const lines = quote.options.map((o) => {
      const price = parseFloat(o.price) === 0 ? t.free : `${o.currency} ${o.price}`;
      return `• ${o.method}: ${price}${t.days(o.daysMin, o.daysMax)}`;
    });
    return { reply: `${t.shippingHead(quote.country, top.name)}\n${lines.join("\n")}`, products: [top], mode: "guided" };
  }

  const products = await searchProducts(last, { limit: 3 });
  if (products.length === 0) return { reply: t.notFound, products: [], mode: "guided" };
  return { reply: t.found, products, mode: "guided" };
}

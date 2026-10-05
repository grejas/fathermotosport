import "server-only";

import { cache } from "react";
import { getTranslations } from "next-intl/server";
import { getShippingReturns } from "@/lib/api/shippingReturns";
import { CONTACT_EMAIL } from "@/lib/data/contact";
import { htmlToText, sanitizePolicyHtml } from "@/lib/sanitizePolicyHtml";

const DEFAULT_HOURS = 48;
const DEFAULT_WHATSAPP = process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384";

/** Textos del acordeón de la ficha de producto (se pasan a un client component). */
export interface ShippingReturnsSummary {
  badgeShipping: string;
  badgeReturns: string;
  summaryShipping: string;
  /** Párrafos de la política de devoluciones. */
  paragraphs: string[];
}

export interface ShippingReturnsContent {
  summary: ShippingReturnsSummary;
  page: {
    title: string;
    /** HTML ya sanitizado, listo para dangerouslySetInnerHTML. */
    bodyHtml: string;
    description: string;
    help: string;
  };
  contact: { email: string; whatsappDigits: string };
}

const escapeHtml = (text: string) =>
  text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

/**
 * Política de envío y devoluciones ya resuelta para un idioma: el valor del panel
 * si existe, o el de messages/*.json si el campo está vacío o la API no responde.
 * En ambos casos {hours} se reemplaza por el plazo del panel.
 *
 * Los textos de messages se leen con t.raw porque llevan {hours} literal.
 */
export const getShippingReturnsContent = cache(async (locale: string): Promise<ShippingReturnsContent> => {
  const [api, tSummary, tPolicy] = await Promise.all([
    getShippingReturns(locale),
    getTranslations({ locale, namespace: "shipping_returns" }),
    getTranslations({ locale, namespace: "returns_policy" }),
  ]);

  const hours = String(api?.damage_report_hours ?? DEFAULT_HOURS);
  const fill = (text: string) => text.replace(/\{hours\}/g, hours);
  const summaryRaw = (key: string) => fill(tSummary.raw(key) as string);
  const policyRaw = (key: string) => fill(tPolicy.raw(key) as string);
  const policyList = (key: string) => (tPolicy.raw(key) as string[]).map(fill);

  const texts = api?.texts;

  // Cuerpo por defecto: el mismo formato que el seeder carga en el RichEditor.
  const fallbackBody = () => {
    const section = (titleKey: string, itemsKey: string, list: "ul" | "ol" = "ul") =>
      `<h2>${escapeHtml(policyRaw(titleKey))}</h2><${list}>${policyList(itemsKey)
        .map((item) => `<li>${escapeHtml(item)}</li>`)
        .join("")}</${list}>`;

    return (
      `<p>${escapeHtml(policyRaw("intro"))}</p>` +
      section("damaged_title", "damaged_items") +
      section("not_accepted_title", "not_accepted_items") +
      section("process_title", "process_items", "ol") +
      section("cancellations_title", "cancellations_items")
    );
  };

  const bodyHtml = sanitizePolicyHtml(texts?.page_body ? fill(texts.page_body) : fallbackBody());
  const plain = htmlToText(bodyHtml);

  return {
    summary: {
      badgeShipping: summaryRaw("free_shipping"),
      badgeReturns: texts?.badge_returns ? fill(texts.badge_returns) : summaryRaw("free_returns"),
      summaryShipping: summaryRaw("summary_shipping"),
      paragraphs: texts?.summary
        ? fill(texts.summary)
            .split(/\n\s*\n/)
            .map((p) => p.trim())
            .filter(Boolean)
        : [summaryRaw("summary_damaged"), summaryRaw("summary_not_accepted")],
    },
    page: {
      title: texts?.page_title ? fill(texts.page_title) : policyRaw("title"),
      bodyHtml,
      description: plain.length > 160 ? `${plain.slice(0, 157).trimEnd()}…` : plain,
      help: policyRaw("help"),
    },
    contact: {
      email: api?.contact.email || CONTACT_EMAIL,
      whatsappDigits: (api?.contact.whatsapp || DEFAULT_WHATSAPP).replace(/\D/g, ""),
    },
  };
});

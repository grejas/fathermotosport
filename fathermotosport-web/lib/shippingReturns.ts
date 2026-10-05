import "server-only";

import { cache } from "react";
import { getTranslations } from "next-intl/server";
import { getShippingReturns } from "@/lib/api/shippingReturns";
import { CONTACT_EMAIL } from "@/lib/data/contact";

const DEFAULT_HOURS = 48;
const DEFAULT_DAYS = 7;
const DEFAULT_WHATSAPP = process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384";

/** Textos del acordeón de la ficha de producto (se pasan a un client component). */
export interface ShippingReturnsSummary {
  badgeShipping: string;
  badgeReturns: string;
  summaryShipping: string;
  summaryDamaged: string;
  summaryWithdrawal: string;
}

export interface ShippingReturnsContent {
  summary: ShippingReturnsSummary;
  page: {
    title: string;
    intro: string;
    damaged: { title: string; items: string[] };
    withdrawal: { title: string; items: string[] };
    process: { title: string; items: string[] };
    cancellations: { title: string; items: string[] };
    help: string;
  };
  contact: { email: string; whatsappDigits: string };
}

/**
 * Textos de envío y devoluciones ya resueltos para un idioma: el valor del panel
 * si existe, o el de messages/*.json si el campo está vacío o la API no responde.
 * En ambos casos {hours} y {days} se reemplazan por los plazos del panel.
 *
 * Los textos de respaldo se leen con t.raw: llevan {hours}/{days} literales y se
 * reemplazan acá, igual que los que vienen de la API.
 */
export const getShippingReturnsContent = cache(async (locale: string): Promise<ShippingReturnsContent> => {
  const [api, tSummary, tPolicy] = await Promise.all([
    getShippingReturns(locale),
    getTranslations({ locale, namespace: "shipping_returns" }),
    getTranslations({ locale, namespace: "returns_policy" }),
  ]);

  const hours = String(api?.damage_report_hours ?? DEFAULT_HOURS);
  const days = String(api?.withdrawal_days ?? DEFAULT_DAYS);
  const fill = (text: string) => text.replace(/\{hours\}/g, hours).replace(/\{days\}/g, days);

  const texts = api?.texts;
  const summaryText = (field: keyof NonNullable<typeof texts>, key: string) =>
    fill((texts?.[field] as string | null) || (tSummary.raw(key) as string));
  const policyText = (field: keyof NonNullable<typeof texts>, key: string) =>
    fill((texts?.[field] as string | null) || (tPolicy.raw(key) as string));
  const policyList = (field: keyof NonNullable<typeof texts>, key: string) => {
    const items = texts?.[field] as string[] | null | undefined;
    return (items?.length ? items : (tPolicy.raw(key) as string[])).map(fill);
  };

  return {
    summary: {
      badgeShipping: summaryText("badge_shipping", "free_shipping"),
      badgeReturns: summaryText("badge_returns", "free_returns"),
      summaryShipping: summaryText("summary_shipping", "summary_shipping"),
      summaryDamaged: summaryText("summary_damaged", "summary_damaged"),
      summaryWithdrawal: summaryText("summary_withdrawal", "summary_withdrawal"),
    },
    page: {
      title: policyText("page_title", "title"),
      intro: policyText("page_intro", "intro"),
      damaged: { title: policyText("damaged_title", "damaged_title"), items: policyList("damaged_items", "damaged_items") },
      withdrawal: {
        title: policyText("withdrawal_title", "withdrawal_title"),
        items: policyList("withdrawal_items", "withdrawal_items"),
      },
      process: { title: policyText("process_title", "process_title"), items: policyList("process_items", "process_items") },
      cancellations: {
        title: policyText("cancellations_title", "cancellations_title"),
        items: policyList("cancellations_items", "cancellations_items"),
      },
      help: policyText("help_text", "help"),
    },
    contact: {
      email: api?.contact.email || CONTACT_EMAIL,
      whatsappDigits: (api?.contact.whatsapp || DEFAULT_WHATSAPP).replace(/\D/g, ""),
    },
  };
});

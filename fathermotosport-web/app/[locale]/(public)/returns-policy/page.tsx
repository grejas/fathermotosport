import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { MessageCircle } from "lucide-react";
import { ContactEmail } from "@/components/shared/ContactEmail";
import { getShippingReturnsContent } from "@/lib/shippingReturns";

export async function generateMetadata({
  params: { locale },
}: {
  params: { locale: string };
}): Promise<Metadata> {
  const { page } = await getShippingReturnsContent(locale);

  return { title: page.title, description: page.description };
}

export default async function ReturnsPolicyPage({
  params: { locale },
}: {
  params: { locale: string };
}) {
  const t = await getTranslations({ locale, namespace: "returns_policy" });
  const { page, contact } = await getShippingReturnsContent(locale);
  const tWhatsapp = await getTranslations({ locale, namespace: "whatsapp" });

  const whatsappUrl = `https://wa.me/${contact.whatsappDigits}?text=${encodeURIComponent(
    tWhatsapp("general_message")
  )}`;

  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-20 sm:px-6 sm:pt-24">
      <h1 className="text-3xl font-extrabold text-brand-white sm:text-4xl">{page.title}</h1>

      {/* Cuerpo editable desde el panel; bodyHtml ya viene sanitizado en el servidor. */}
      <div
        className="prose prose-sm prose-invert mt-6 max-w-none text-brand-muted prose-headings:font-bold prose-headings:text-brand-white prose-h2:mb-3 prose-h2:mt-8 prose-h2:text-lg prose-p:text-brand-muted prose-a:text-brand-red prose-a:no-underline hover:prose-a:underline prose-strong:text-brand-white prose-li:text-brand-muted marker:font-bold marker:text-brand-red"
        dangerouslySetInnerHTML={{ __html: page.bodyHtml }}
      />

      <div className="mt-8 rounded-2xl border border-white/10 bg-brand-card p-5 text-center sm:p-6">
        <p className="text-sm text-brand-muted">{page.help}</p>
        <div className="mt-4 flex flex-col items-stretch gap-3 sm:flex-row sm:justify-center">
          <a
            href={whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110"
          >
            <MessageCircle size={16} />
            {t("contact_whatsapp")}
          </a>
          <ContactEmail email={contact.email} />
        </div>
      </div>
    </div>
  );
}

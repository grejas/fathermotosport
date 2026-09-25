import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AlertCircle, MessageCircle, PackageCheck, RotateCcw, XCircle } from "lucide-react";
import { ContactEmail } from "@/components/shared/ContactEmail";

const whatsappNumber = (process.env.NEXT_PUBLIC_WHATSAPP ?? "+59168736384").replace(/\D/g, "");

export async function generateMetadata({
  params: { locale },
}: {
  params: { locale: string };
}): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "returns_policy" });

  return { title: t("title"), description: t("intro") };
}

/** Sección con ícono, título y lista de puntos. */
function Section({
  icon: Icon,
  title,
  items,
  ordered = false,
}: {
  icon: typeof RotateCcw;
  title: string;
  items: string[];
  ordered?: boolean;
}) {
  const List = ordered ? "ol" : "ul";

  return (
    <section className="rounded-2xl border border-white/10 bg-brand-card p-5 sm:p-6">
      <h2 className="flex items-center gap-2.5 text-lg font-bold text-brand-white">
        <Icon size={20} className="shrink-0 text-brand-red" />
        {title}
      </h2>
      <List
        className={
          ordered
            ? "mt-4 list-decimal space-y-2 pl-5 text-sm text-brand-muted marker:font-bold marker:text-brand-red"
            : "mt-4 space-y-2 text-sm text-brand-muted"
        }
      >
        {items.map((item, i) => (
          <li key={i} className={ordered ? "pl-1" : "flex gap-2"}>
            {ordered ? (
              item
            ) : (
              <>
                <span aria-hidden className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-red" />
                <span>{item}</span>
              </>
            )}
          </li>
        ))}
      </List>
    </section>
  );
}

export default async function ReturnsPolicyPage({
  params: { locale },
}: {
  params: { locale: string };
}) {
  const t = await getTranslations({ locale, namespace: "returns_policy" });
  const tWhatsapp = await getTranslations({ locale, namespace: "whatsapp" });

  const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(
    tWhatsapp("general_message")
  )}`;

  const items = (key: string) => t.raw(key) as string[];

  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-20 sm:px-6 sm:pt-24">
      <h1 className="text-3xl font-extrabold text-brand-white sm:text-4xl">{t("title")}</h1>
      <p className="mt-3 text-sm text-brand-muted">{t("intro")}</p>

      <div className="mt-8 space-y-5">
        <Section icon={AlertCircle} title={t("damaged_title")} items={items("damaged_items")} />
        <Section icon={RotateCcw} title={t("withdrawal_title")} items={items("withdrawal_items")} />
        <Section icon={PackageCheck} title={t("process_title")} items={items("process_items")} ordered />
        <Section icon={XCircle} title={t("cancellations_title")} items={items("cancellations_items")} />
      </div>

      <div className="mt-8 rounded-2xl border border-white/10 bg-brand-card p-5 text-center sm:p-6">
        <p className="text-sm text-brand-muted">{t("help")}</p>
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
          <ContactEmail />
        </div>
      </div>
    </div>
  );
}

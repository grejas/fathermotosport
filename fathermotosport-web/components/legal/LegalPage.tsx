import { Link } from "@/lib/i18n/navigation";


export interface LegalSection {
  heading: string;
  /** Párrafos de la sección; cada elemento puede ser texto o una lista (<ul>). */
  body: (string | { list: string[] } | { text: string; href: string; linkText: string })[];
}

// Misma tipografía que /returns-policy.
const PROSE =
  "prose prose-sm prose-invert mt-6 max-w-none text-brand-muted prose-headings:font-bold prose-headings:text-brand-white prose-h2:mb-3 prose-h2:mt-8 prose-h2:text-lg prose-p:text-brand-muted prose-a:text-brand-red prose-a:no-underline hover:prose-a:underline prose-strong:text-brand-white prose-li:text-brand-muted marker:font-bold marker:text-brand-red";

/** Plantilla de páginas legales estáticas (Términos, Privacidad). Contenido en español. */
export function LegalPage({
  title,
  intro,
  sections,
  updated,
}: {
  title: string;
  intro?: string;
  sections: LegalSection[];
  updated: string;
}) {
  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-20 sm:px-6 sm:pt-24">
      <h1 className="text-3xl font-extrabold text-brand-white sm:text-4xl">{title}</h1>
      <div className={PROSE}>
        {intro && <p>{intro}</p>}
        {sections.map((s) => (
          <section key={s.heading}>
            <h2>{s.heading}</h2>
            {s.body.map((b, i) =>
              typeof b === "string" ? (
                <p key={i}>{b}</p>
              ) : "list" in b ? (
                <ul key={i}>
                  {b.list.map((li) => (
                    <li key={li}>{li}</li>
                  ))}
                </ul>
              ) : (
                <p key={i}>
                  {b.text} <Link href={b.href}>{b.linkText}</Link>
                </p>
              )
            )}
          </section>
        ))}
        <p className="mt-8 text-xs">{updated}</p>
      </div>
    </div>
  );
}


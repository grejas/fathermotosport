"use client";

import { useLocale } from "next-intl";
import { usePathname, useRouter } from "@/lib/i18n/navigation";
import { cn } from "@/lib/utils";

const LANGS = [
  { code: "es", label: "ES" },
  { code: "pt", label: "PT" },
  { code: "en", label: "EN" },
] as const;

export function LanguageSelector({ className }: { className?: string }) {
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();

  return (
    <div
      className={cn(
        "flex items-center gap-0.5 rounded-lg border border-white/10 bg-white/5 p-0.5",
        className
      )}
    >
      {LANGS.map(({ code, label }) => (
        <button
          key={code}
          type="button"
          onClick={() => {
            if (code === locale) return;
            router.replace(pathname, { locale: code });
          }}
          className={cn(
            "notranslate rounded-md px-2 py-1 text-xs font-semibold transition",
            locale === code
              ? "bg-brand-red text-white"
              : "text-brand-muted hover:bg-white/10 hover:text-brand-white"
          )}
          aria-label={`Cambiar idioma a ${label}`}
          aria-pressed={locale === code}
        >
          {label}
        </button>
      ))}
    </div>
  );
}

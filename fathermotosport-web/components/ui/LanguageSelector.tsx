"use client";

import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

type LangCode = "es" | "pt" | "en";

const LANGS: { code: LangCode; label: string }[] = [
  { code: "es", label: "ES" },
  { code: "pt", label: "PT" },
  { code: "en", label: "EN" },
];

function readCookieLang(): LangCode {
  const match = document.cookie.match(/googtrans=\/es\/(pt|en)/);
  return (match?.[1] as LangCode) ?? "es";
}

function setGoogTransCookie(lang: LangCode) {
  const domain = window.location.hostname;
  if (lang === "es") {
    document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${domain}`;
    return;
  }
  document.cookie = `googtrans=/es/${lang}; path=/`;
  document.cookie = `googtrans=/es/${lang}; path=/; domain=${domain}`;
}

export function LanguageSelector() {
  const [active, setActive] = useState<LangCode>("es");

  useEffect(() => {
    setActive(readCookieLang());
  }, []);

  const selectLanguage = (lang: LangCode) => {
    if (lang === active) return;
    setGoogTransCookie(lang);
    window.location.reload();
  };

  return (
    <div className="flex items-center gap-0.5 rounded-lg border border-white/10 bg-white/5 p-0.5">
      {LANGS.map(({ code, label }) => (
        <button
          key={code}
          type="button"
          onClick={() => selectLanguage(code)}
          className={cn(
            "notranslate rounded-md px-2 py-1 text-xs font-semibold transition",
            active === code
              ? "bg-brand-red text-white"
              : "text-brand-muted hover:bg-white/10 hover:text-brand-white"
          )}
          aria-label={`Cambiar idioma a ${label}`}
          aria-pressed={active === code}
        >
          {label}
        </button>
      ))}
    </div>
  );
}

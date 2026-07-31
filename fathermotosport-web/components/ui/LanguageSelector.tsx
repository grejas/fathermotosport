"use client";

import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

type LangCode = "es" | "pt" | "en";

const LANGS: { code: LangCode; label: string }[] = [
  { code: "es", label: "ES" },
  { code: "pt", label: "PT" },
  { code: "en", label: "EN" },
];

const ORIGINAL_URL = "https://fathermotosport.com";
const TRANSLATE_HOST = "fathermotosport-com.translate.goog";

function buildUrl(lang: LangCode): string {
  if (lang === "es") return ORIGINAL_URL;
  return `https://${TRANSLATE_HOST}/?_x_tr_sl=es&_x_tr_tl=${lang}&_x_tr_hl=es`;
}

function readActiveLang(): LangCode {
  if (!window.location.hostname.includes("translate.goog")) return "es";
  const tl = new URLSearchParams(window.location.search).get("_x_tr_tl");
  return tl === "pt" || tl === "en" ? tl : "es";
}

export function LanguageSelector() {
  const [active, setActive] = useState<LangCode>("es");

  useEffect(() => {
    setActive(readActiveLang());
  }, []);

  return (
    <div className="flex items-center gap-0.5 rounded-lg border border-white/10 bg-white/5 p-0.5">
      {LANGS.map(({ code, label }) => (
        <button
          key={code}
          type="button"
          onClick={() => {
            if (code === active) return;
            window.location.href = buildUrl(code);
          }}
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

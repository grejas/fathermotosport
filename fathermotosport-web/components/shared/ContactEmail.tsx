"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Check, Copy, Mail } from "lucide-react";
import { CONTACT_EMAIL } from "@/lib/data/contact";

/**
 * Correo de contacto con dos caminos: el enlace mailto: (que depende del cliente de
 * correo del dispositivo y puede no abrir nada si no hay uno configurado) y un botón
 * para copiar la dirección, que funciona siempre.
 */
export function ContactEmail({ email = CONTACT_EMAIL }: { email?: string }) {
  const t = useTranslations("returns_policy");
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(email);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Sin permiso de portapapeles: la dirección igual está visible para copiarla a mano.
    }
  };

  return (
    <div className="flex flex-col items-center gap-2 sm:flex-row sm:justify-center">
      <a
        href={`mailto:${email}`}
        className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-brand-white transition hover:border-white/30 hover:bg-white/10"
      >
        <Mail size={16} />
        {email}
      </a>
      <button
        type="button"
        onClick={copy}
        className="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-brand-muted transition hover:text-brand-white"
      >
        {copied ? <Check size={14} className="text-cat-boots" /> : <Copy size={14} />}
        {copied ? t("copied") : t("copy_email")}
      </button>
    </div>
  );
}

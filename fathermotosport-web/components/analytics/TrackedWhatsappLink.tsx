"use client";

import type { AnchorHTMLAttributes, ReactNode } from "react";
import { trackWhatsappClick } from "@/lib/analytics/events";

/**
 * Enlace a WhatsApp que registra el clic en los píxeles. Existe para usarse dentro de
 * componentes de servidor (pie de página, páginas legales), que no pueden tener onClick.
 *
 * `origen` identifica de dónde salió el clic: footer, returns_policy, etc.
 */
export function TrackedWhatsappLink({
  href,
  origen,
  children,
  ...rest
}: { href: string; origen: string; children: ReactNode } & Omit<
  AnchorHTMLAttributes<HTMLAnchorElement>,
  "href" | "onClick"
>) {
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" {...rest} onClick={() => trackWhatsappClick(origen)}>
      {children}
    </a>
  );
}

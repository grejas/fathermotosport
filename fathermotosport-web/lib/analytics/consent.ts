"use client";

import { useEffect, useState } from "react";

/**
 * Consentimiento de cookies (RGPD + ePrivacy en la UE, LGPD en Brasil, CCPA en EE.UU.).
 *
 * - Necesarias (carrito, sesión, idioma, prevención de fraude de Stripe): siempre activas.
 * - Analítica (Google Analytics 4): solo con consentimiento.
 * - Marketing (píxeles de Meta y TikTok): solo con consentimiento.
 *
 * Hasta que el visitante elige, no se carga ningún píxel ni GA4. La elección se guarda en
 * este navegador (localStorage) y se puede cambiar desde "Preferencias de cookies" en el pie.
 * Subir CONSENT_VERSION vuelve a preguntar a todos (por ejemplo, si se agrega otro proveedor).
 */

export const CONSENT_VERSION = 1;
const KEY = "fms-consent";
const CHANGE_EVENT = "fms-consent-change";
const OPEN_EVENT = "fms-consent-open";

export interface Consent {
  analytics: boolean;
  marketing: boolean;
  /** Versión del texto/proveedores que aceptó. */
  v: number;
  /** Momento de la elección (ISO), como registro del consentimiento. */
  at: string;
}

export function readConsent(): Consent | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(KEY);
    if (!raw) return null;
    const c = JSON.parse(raw) as Partial<Consent>;
    if (c.v !== CONSENT_VERSION) return null;
    return { analytics: !!c.analytics, marketing: !!c.marketing, v: CONSENT_VERSION, at: String(c.at ?? "") };
  } catch {
    return null;
  }
}

export function saveConsent(choice: { analytics: boolean; marketing: boolean }): void {
  const c: Consent = { ...choice, v: CONSENT_VERSION, at: new Date().toISOString() };
  try {
    window.localStorage.setItem(KEY, JSON.stringify(c));
  } catch {
    // Navegador sin almacenamiento: la elección vale solo para esta visita.
  }
  window.dispatchEvent(new CustomEvent<Consent>(CHANGE_EVENT, { detail: c }));
}

/** Reabre el banner (enlace "Preferencias de cookies" del pie). */
export function openConsentSettings(): void {
  window.dispatchEvent(new Event(OPEN_EVENT));
}

/** Consentimiento actual; `undefined` mientras se lee en el navegador, `null` si no eligió. */
export function useConsent(): Consent | null | undefined {
  const [consent, setConsent] = useState<Consent | null | undefined>(undefined);

  useEffect(() => {
    setConsent(readConsent());
    const onChange = (e: Event) => setConsent((e as CustomEvent<Consent>).detail);
    window.addEventListener(CHANGE_EVENT, onChange);
    return () => window.removeEventListener(CHANGE_EVENT, onChange);
  }, []);

  return consent;
}

export function useConsentOpenRequest(onOpen: () => void): void {
  useEffect(() => {
    window.addEventListener(OPEN_EVENT, onOpen);
    return () => window.removeEventListener(OPEN_EVENT, onOpen);
  }, [onOpen]);
}

"use client";

import { useEffect, useState } from "react";

export interface CountdownSegment {
  value: number;
  /** Clave de traducción en el namespace flash_promo: days | hours | minutes | seconds. */
  unitKey: "days" | "hours" | "minutes" | "seconds";
}

/*
 * Reloj compartido a nivel de módulo: un ÚNICO setInterval alimenta a todos los
 * consumidores de useCountdown con el mismo valor `now` en el mismo instante.
 * Así, dos banners que apunten al mismo ends_at muestran siempre exactamente el
 * mismo tiempo restante (no hay intervalos independientes que se desfasen por
 * arrancar en momentos distintos).
 */
let sharedNow = Date.now();
const listeners = new Set<(now: number) => void>();
let timer: ReturnType<typeof setInterval> | null = null;

function ensureTicker() {
  if (timer !== null) return;
  sharedNow = Date.now();
  timer = setInterval(() => {
    sharedNow = Date.now();
    listeners.forEach((notify) => notify(sharedNow));
  }, 1_000);
}

function subscribeToClock(notify: (now: number) => void): () => void {
  ensureTicker();
  listeners.add(notify);
  // Alinea de inmediato al reloj compartido para no esperar al primer tick.
  notify(sharedNow);
  return () => {
    listeners.delete(notify);
    if (listeners.size === 0 && timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  };
}

/**
 * Contador regresivo real basado en un timestamp ISO. El tiempo restante SIEMPRE
 * se deriva del `endsAtIso` real del servidor menos el reloj compartido: nunca de
 * un contador local que se decremente por su cuenta. Actualiza una vez por segundo
 * mientras `enabled` sea true, en fase con todas las demás instancias del hook.
 */
export function useCountdown(endsAtIso: string | null | undefined, enabled = true) {
  const endsAt = endsAtIso ? new Date(endsAtIso).getTime() : null;
  const [now, setNow] = useState(() => sharedNow);

  useEffect(() => {
    if (!enabled || !endsAt) return;
    return subscribeToClock(setNow);
  }, [enabled, endsAt]);

  const remaining = endsAt ? Math.max(0, Math.floor((endsAt - now) / 1000)) : 0;

  const days = Math.floor(remaining / 86_400);
  const hours = Math.floor((remaining % 86_400) / 3_600);
  const minutes = Math.floor((remaining % 3_600) / 60);
  const seconds = remaining % 60;

  const segments: CountdownSegment[] = [];
  if (days > 0) segments.push({ value: days, unitKey: "days" });
  if (days > 0 || hours > 0) segments.push({ value: hours, unitKey: "hours" });
  segments.push({ value: minutes, unitKey: "minutes" });
  segments.push({ value: seconds, unitKey: "seconds" });

  return { remaining, segments };
}

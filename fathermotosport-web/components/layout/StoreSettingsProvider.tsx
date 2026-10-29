"use client";

import { createContext, useContext, type ReactNode } from "react";
import { FALLBACK_STORE_SETTINGS, type StoreSettings } from "@/lib/api/storeSettings";

const StoreSettingsContext = createContext<StoreSettings>(FALLBACK_STORE_SETTINGS);

/** Ajustes de la tienda leídos en el servidor (app/[locale]/layout.tsx) para los componentes de cliente. */
export function StoreSettingsProvider({ value, children }: { value: StoreSettings; children: ReactNode }) {
  return <StoreSettingsContext.Provider value={value}>{children}</StoreSettingsContext.Provider>;
}

/** Correo, WhatsApp y redes del panel; fuera del provider, los de respaldo. */
export function useStoreSettings(): StoreSettings {
  return useContext(StoreSettingsContext);
}

"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ShieldAlert, X } from "lucide-react";
import { useAuthStore } from "@/store/authStore";
import { useMounted } from "@/lib/hooks/useMounted";
import apiClient from "@/lib/api/client";

/**
 * Banner NO intrusivo que sugiere actualizar la contraseña cada 14 días.
 * No bloquea nada: el usuario puede posponerlo o actualizar cuando quiera.
 */
export function SecurityReminderBanner() {
  const router = useRouter();
  const mounted = useMounted();
  const user = useAuthStore((s) => s.user);
  const refreshUser = useAuthStore((s) => s.refreshUser);
  const [dismissed, setDismissed] = useState(false);

  if (!mounted || dismissed || !user?.needs_security_reminder) return null;

  const remindLater = async () => {
    setDismissed(true);
    try {
      await apiClient.post("/user/dismiss-security-reminder");
      await refreshUser();
    } catch {
      /* si falla la red, igual lo ocultamos en esta sesión */
    }
  };

  return (
    <div className="mb-6 flex flex-col items-start gap-3 rounded-2xl border border-brand-gold/30 bg-brand-gold/10 p-4 sm:flex-row sm:items-center">
      <ShieldAlert className="shrink-0 text-brand-gold" size={22} />
      <p className="flex-1 text-sm text-brand-white">
        Por tu seguridad, te recomendamos actualizar tu contraseña.
      </p>
      <div className="flex items-center gap-2">
        <button
          onClick={() => router.push("/profile?security=password")}
          className="rounded-lg bg-brand-gold px-3 py-1.5 text-xs font-semibold text-brand-carbon transition hover:opacity-90"
        >
          Actualizar ahora
        </button>
        <button
          onClick={remindLater}
          className="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-medium text-brand-muted transition hover:text-brand-white"
        >
          Recordármelo después
        </button>
        <button onClick={remindLater} aria-label="Cerrar" className="p-1 text-brand-muted hover:text-brand-white">
          <X size={16} />
        </button>
      </div>
    </div>
  );
}

"use client";

import { CreditCard, Wallet } from "lucide-react";
import type { PaymentMethod } from "@/lib/types";
import { cn } from "@/lib/utils";

const methods: { id: PaymentMethod; label: string; desc: string; icon: typeof Wallet }[] = [
  { id: "paypal", label: "PayPal", desc: "Paga con tu cuenta PayPal", icon: Wallet },
  { id: "stripe", label: "Tarjeta", desc: "Visa, MasterCard, Amex", icon: CreditCard },
  { id: "mercadopago", label: "MercadoPago", desc: "Tarjeta, QR o saldo", icon: Wallet },
];

interface Props {
  value: PaymentMethod;
  onChange: (m: PaymentMethod) => void;
  /** Métodos habilitados; el resto se muestra en gris con "Próximamente". */
  enabled?: readonly PaymentMethod[];
  comingSoonLabel?: string;
}

export function PaymentMethods({ value, onChange, enabled, comingSoonLabel }: Props) {
  return (
    <div className="space-y-3">
      {methods.map((m) => {
        const Icon = m.icon;
        const disponible = !enabled || enabled.includes(m.id);
        const active = disponible && value === m.id;
        return (
          <button
            key={m.id}
            type="button"
            disabled={!disponible}
            onClick={() => onChange(m.id)}
            className={cn(
              "flex w-full items-center gap-3 rounded-xl border p-4 text-left transition",
              active
                ? "border-brand-red bg-brand-red/10"
                : "border-white/10 hover:border-white/30",
              !disponible && "cursor-not-allowed opacity-40 hover:border-white/10"
            )}
          >
            <span
              className={cn(
                "flex h-10 w-10 items-center justify-center rounded-lg",
                // PayPal conserva su amarillo también cuando está elegido.
                m.id === "paypal"
                  ? "bg-[#FFC439] text-[#003087]"
                  : active
                    ? "bg-brand-red text-white"
                    : "bg-white/5 text-brand-muted"
              )}
            >
              <Icon size={18} />
            </span>
            <div className="flex-1">
              <p className="text-sm font-semibold text-brand-white">{m.label}</p>
              <p className="text-xs text-brand-muted">
                {disponible ? m.desc : comingSoonLabel ?? m.desc}
              </p>
            </div>
            {disponible && (
              <span
                className={cn(
                  "h-4 w-4 rounded-full border-2",
                  active ? "border-brand-red bg-brand-red" : "border-white/30"
                )}
              />
            )}
          </button>
        );
      })}
    </div>
  );
}

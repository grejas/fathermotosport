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
}

export function PaymentMethods({ value, onChange }: Props) {
  return (
    <div className="space-y-3">
      {methods.map((m) => {
        const Icon = m.icon;
        const active = value === m.id;
        return (
          <button
            key={m.id}
            type="button"
            onClick={() => onChange(m.id)}
            className={cn(
              "flex w-full items-center gap-3 rounded-xl border p-4 text-left transition",
              active
                ? "border-brand-red bg-brand-red/10"
                : "border-white/10 hover:border-white/30"
            )}
          >
            <span
              className={cn(
                "flex h-10 w-10 items-center justify-center rounded-lg",
                active ? "bg-brand-red text-white" : "bg-white/5 text-brand-muted"
              )}
            >
              <Icon size={18} />
            </span>
            <div className="flex-1">
              <p className="text-sm font-semibold text-brand-white">{m.label}</p>
              <p className="text-xs text-brand-muted">{m.desc}</p>
            </div>
            <span
              className={cn(
                "h-4 w-4 rounded-full border-2",
                active ? "border-brand-red bg-brand-red" : "border-white/30"
              )}
            />
          </button>
        );
      })}
    </div>
  );
}

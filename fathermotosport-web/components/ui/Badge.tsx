import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

type Variant = "red" | "gold" | "green" | "gray";

const variants: Record<Variant, string> = {
  red: "bg-brand-red/15 text-brand-red ring-brand-red/30",
  gold: "bg-brand-gold/15 text-brand-gold ring-brand-gold/30",
  green: "bg-cat-boots/15 text-cat-boots ring-cat-boots/30",
  gray: "bg-white/10 text-brand-muted ring-white/15",
};

interface BadgeProps {
  variant?: Variant;
  children: ReactNode;
  className?: string;
}

export function Badge({ variant = "gray", children, className }: BadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide ring-1",
        variants[variant],
        className
      )}
    >
      {children}
    </span>
  );
}

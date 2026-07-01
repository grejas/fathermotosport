"use client";

import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from "react";
import { cn } from "@/lib/utils";
import { Spinner } from "./Spinner";

type Variant = "primary" | "glass" | "gold";
type Size = "sm" | "md" | "lg";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  icon?: ReactNode;
  children?: ReactNode;
}

const base =
  "inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50 hover:opacity-95";

const variants: Record<Variant, string> = {
  primary: "bg-brand-red text-white hover:shadow-red-glow",
  glass: "glass text-brand-white hover:bg-white/10",
  gold: "bg-brand-gold text-brand-carbon hover:shadow-gold-glow",
};

const sizes: Record<Size, string> = {
  sm: "px-3.5 py-2 text-sm",
  md: "px-5 py-3 text-sm",
  lg: "px-7 py-4 text-base",
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = "primary", size = "md", loading, icon, children, className, disabled, ...props }, ref) => (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={cn(base, variants[variant], sizes[size], className)}
      {...props}
    >
      {loading ? <Spinner size={16} /> : icon}
      {children}
    </button>
  )
);

Button.displayName = "Button";

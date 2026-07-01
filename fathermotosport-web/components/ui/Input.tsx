"use client";

import { forwardRef, type InputHTMLAttributes, type ReactNode } from "react";
import { cn } from "@/lib/utils";

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, hint, leftIcon, rightIcon, className, id, ...props }, ref) => {
    const inputId = id ?? props.name;
    return (
      <div className="w-full">
        {label && (
          <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-brand-white">
            {label}
          </label>
        )}
        <div className="relative">
          {leftIcon && (
            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand-muted">
              {leftIcon}
            </span>
          )}
          <input
            ref={ref}
            id={inputId}
            className={cn(
              "input-brand",
              leftIcon && "pl-10",
              rightIcon && "pr-10",
              error && "border-brand-red/70 focus:ring-brand-red/30",
              className
            )}
            {...props}
          />
          {rightIcon && (
            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-brand-muted">
              {rightIcon}
            </span>
          )}
        </div>
        {error ? (
          <p className="mt-1.5 text-xs text-brand-red">{error}</p>
        ) : hint ? (
          <p className="mt-1.5 text-xs text-brand-muted">{hint}</p>
        ) : null}
      </div>
    );
  }
);

Input.displayName = "Input";

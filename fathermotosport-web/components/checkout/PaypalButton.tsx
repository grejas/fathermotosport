"use client";

import { Spinner } from "@/components/ui/Spinner";
import { cn } from "@/lib/utils";

interface PaypalButtonProps {
  /** Texto antes del logo, ej. "Pagar con". */
  label: string;
  /** Texto completo para lectores de pantalla, ej. "Pagar con PayPal". */
  ariaLabel: string;
  loading?: boolean;
  disabled?: boolean;
  className?: string;
  /**
   * Si se pasa, el botón actúa por sí mismo en vez de enviar un formulario. Lo necesita
   * la venta cruzada post-compra, que no tiene formulario alrededor.
   */
  onClick?: () => void;
}

/**
 * Botón de pago con los colores de PayPal (amarillo #FFC439 y azul #003087/#009CDE),
 * como el del checkout de PayPal. El logotipo se arma con el nombre en dos tonos en
 * vez de incrustar la marca oficial.
 */
export function PaypalButton({
  label,
  ariaLabel,
  loading,
  disabled,
  className,
  onClick,
}: PaypalButtonProps) {
  return (
    <button
      type={onClick ? "button" : "submit"}
      onClick={onClick}
      disabled={loading || disabled}
      aria-label={ariaLabel}
      className={cn(
        "inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FFC439] px-5 py-3 text-sm font-semibold text-[#003087]",
        "transition-all hover:brightness-95 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-60",
        className
      )}
    >
      {loading && <Spinner size={16} className="border-[#003087]/25 border-t-[#003087]" />}
      <span>{label}</span>
      <span className="text-base font-extrabold italic tracking-tight" aria-hidden>
        <span className="text-[#003087]">Pay</span>
        <span className="text-[#009cde]">Pal</span>
      </span>
    </button>
  );
}

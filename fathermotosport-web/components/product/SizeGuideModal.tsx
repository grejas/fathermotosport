"use client";

import { useEffect } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";

const SIZES = [
  { size: "XS", range: "53-54 cm" },
  { size: "S", range: "55-56 cm" },
  { size: "M", range: "57-58 cm" },
  { size: "L", range: "59-60 cm" },
  { size: "XL", range: "61-62 cm" },
  { size: "XXL", range: "63-64 cm" },
];

interface SizeGuideModalProps {
  open: boolean;
  onClose: () => void;
}

export function SizeGuideModal({ open, onClose }: SizeGuideModalProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    if (open) document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-[100] flex items-center justify-center p-4"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
        >
          <div
            className="absolute inset-0 bg-black/70 backdrop-blur-sm"
            onClick={onClose}
            aria-hidden
          />
          <motion.div
            className="relative z-10 w-full max-w-md rounded-2xl border border-brand-red bg-black p-6 text-brand-white shadow-red-glow"
            initial={{ opacity: 0, scale: 0.94 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.94 }}
            transition={{ duration: 0.2 }}
          >
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-bold text-brand-white">Guía de tallas</h3>
              <button
                onClick={onClose}
                className="ml-auto rounded-lg p-1 text-brand-muted transition hover:bg-white/10 hover:text-brand-white"
                aria-label="Cerrar"
              >
                <X size={20} />
              </button>
            </div>

            <div className="flex justify-center py-2">
              <svg width="96" height="96" viewBox="0 0 96 96" fill="none" aria-hidden>
                <circle cx="48" cy="42" r="26" stroke="#F5F5F5" strokeWidth="2" />
                <path
                  d="M22 42 A26 10 0 0 0 74 42"
                  stroke="#E8001D"
                  strokeWidth="3"
                  strokeLinecap="round"
                  fill="none"
                />
                <path
                  d="M74 42 L86 38"
                  stroke="#E8001D"
                  strokeWidth="3"
                  strokeLinecap="round"
                />
                <path
                  d="M22 42 L10 38"
                  stroke="#E8001D"
                  strokeWidth="3"
                  strokeLinecap="round"
                />
                <path
                  d="M48 68 Q40 80 30 86 M48 68 Q56 80 66 86"
                  stroke="#F5F5F5"
                  strokeWidth="2"
                  fill="none"
                />
              </svg>
            </div>

            <p className="mb-4 text-center text-sm text-brand-muted">
              Medí la circunferencia de tu cabeza con una cinta métrica a la altura de la frente
            </p>

            <table className="w-full overflow-hidden rounded-lg border border-white/10 text-sm">
              <thead>
                <tr className="bg-white/5">
                  <th className="px-3 py-2 text-left font-semibold text-brand-white">Talla</th>
                  <th className="px-3 py-2 text-left font-semibold text-brand-white">
                    Circunferencia
                  </th>
                </tr>
              </thead>
              <tbody>
                {SIZES.map(({ size, range }) => (
                  <tr key={size} className="border-t border-white/10">
                    <td className="px-3 py-2 font-medium text-brand-white">{size}</td>
                    <td className="px-3 py-2 text-brand-muted">{range}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

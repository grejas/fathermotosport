"use client";

import { useEffect } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";

interface SizeGuide {
  instructions: string;
  columns: string[];
  rows: string[][];
  /** true mientras los valores sean de referencia genérica y no de las marcas reales. */
  placeholder: boolean;
}

// Tablas por slug de categoría. Las categorías sin tabla no muestran el botón de guía.
const GUIDES: Record<string, SizeGuide> = {
  // TODO(admin): verificar contra las tablas oficiales de cada marca de cascos que se vende
  // (Shark, LS2, Arai, Nolan, AGV); algunas marcas desplazan 1 cm los rangos.
  cascos: {
    instructions: "Medí la circunferencia de tu cabeza con una cinta métrica a la altura de la frente",
    columns: ["Talla", "Circunferencia"],
    rows: [
      ["XS", "53-54 cm"],
      ["S", "55-56 cm"],
      ["M", "57-58 cm"],
      ["L", "59-60 cm"],
      ["XL", "61-62 cm"],
      ["XXL", "63-64 cm"],
    ],
    placeholder: false,
  },
  // TODO(admin): PLACEHOLDER — reemplazar con la tabla real de las marcas de guantes que se venden.
  guantes: {
    instructions:
      "Medí la circunferencia de tu mano dominante a la altura de los nudillos, sin incluir el pulgar",
    columns: ["Talla", "Número", "Circunferencia"],
    rows: [
      ["XS", "7", "17-19 cm"],
      ["S", "8", "19-21 cm"],
      ["M", "9", "21-23 cm"],
      ["L", "10", "23-25 cm"],
      ["XL", "11", "25-27 cm"],
      ["XXL", "12", "27-29 cm"],
    ],
    placeholder: true,
  },
  // TODO(admin): PLACEHOLDER — reemplazar con la tabla real de las marcas de botas que se venden.
  botas: {
    instructions: "Medí el largo de tu pie desde el talón hasta el dedo más largo, de pie y con medias",
    columns: ["EU", "US", "Largo del pie"],
    rows: [
      ["39", "6.5", "24.5-25 cm"],
      ["40", "7", "25-25.5 cm"],
      ["41", "8", "25.5-26 cm"],
      ["42", "8.5", "26-26.5 cm"],
      ["43", "9.5", "26.5-27.5 cm"],
      ["44", "10", "27.5-28 cm"],
      ["45", "11", "28-29 cm"],
      ["46", "12", "29-29.5 cm"],
    ],
    placeholder: true,
  },
  // TODO(admin): PLACEHOLDER — reemplazar con la tabla real de las marcas de chamarras que se venden.
  chamarras: {
    instructions: "Medí el contorno de tu pecho en la parte más ancha, con los brazos relajados",
    columns: ["Talla", "Pecho"],
    rows: [
      ["S", "88-96 cm"],
      ["M", "96-104 cm"],
      ["L", "104-112 cm"],
      ["XL", "112-120 cm"],
      ["XXL", "120-128 cm"],
    ],
    placeholder: true,
  },
};

export function hasSizeGuide(categorySlug?: string | null): boolean {
  return !!categorySlug && categorySlug in GUIDES;
}

interface SizeGuideModalProps {
  open: boolean;
  onClose: () => void;
  categorySlug?: string | null;
}

export function SizeGuideModal({ open, onClose, categorySlug }: SizeGuideModalProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    if (open) document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  const guide = categorySlug ? GUIDES[categorySlug] : undefined;

  return (
    <AnimatePresence>
      {open && guide && (
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

            {categorySlug === "cascos" && (
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
            )}

            <p className="mb-4 text-center text-sm text-brand-muted">{guide.instructions}</p>

            <table className="w-full overflow-hidden rounded-lg border border-white/10 text-sm">
              <thead>
                <tr className="bg-white/5">
                  {guide.columns.map((col) => (
                    <th key={col} className="px-3 py-2 text-left font-semibold text-brand-white">
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {guide.rows.map((row) => (
                  <tr key={row[0]} className="border-t border-white/10">
                    {row.map((cell, i) => (
                      <td
                        key={i}
                        className={
                          i === 0
                            ? "px-3 py-2 font-medium text-brand-white"
                            : "px-3 py-2 text-brand-muted"
                        }
                      >
                        {cell}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>

            {guide.placeholder && (
              <p className="mt-3 text-center text-xs text-brand-muted">
                Medidas de referencia. Pueden variar según la marca; ante la duda, consultanos por
                WhatsApp.
              </p>
            )}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

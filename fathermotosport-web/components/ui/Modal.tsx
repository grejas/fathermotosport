"use client";

import { useEffect, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
}

/**
 * Se renderiza en document.body con un portal, no donde se lo invoca.
 *
 * Quedando en el árbol de quien lo usa heredaba su contexto del DOM: dentro del
 * formulario del checkout, el <form> del modal pasaba a ser un form anidado (HTML
 * inválido) y su submit burbujeaba al formulario de afuera, que se volvía a ejecutar.
 * Desde el body eso no puede pasar con ningún modal del sitio.
 */
export function Modal({ open, onClose, title, children }: ModalProps) {
  // En el servidor no existe document.body: el portal solo se crea ya en el navegador.
  const [montado, setMontado] = useState(false);

  useEffect(() => setMontado(true), []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    if (open) document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!montado) return null;

  return createPortal(
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
            className="relative z-10 w-full max-w-lg rounded-2xl border border-white/10 bg-brand-dark p-6 shadow-card-hover"
            initial={{ opacity: 0, scale: 0.94 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.94 }}
            transition={{ duration: 0.2 }}
          >
            <div className="mb-4 flex items-center justify-between">
              {title && <h3 className="text-lg font-bold text-brand-white">{title}</h3>}
              {/* type="button" explícito: un <button> sin type es submit, y si este
                  modal volviera a quedar dentro de un formulario, la X lo enviaría. */}
              <button
                type="button"
                onClick={onClose}
                className="ml-auto rounded-lg p-1 text-brand-muted transition hover:bg-white/10 hover:text-brand-white"
                aria-label="Cerrar"
              >
                <X size={20} />
              </button>
            </div>
            {children}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>,
    document.body
  );
}

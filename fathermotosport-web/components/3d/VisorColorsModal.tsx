"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { ArrowLeft } from "lucide-react";
import { Modal } from "@/components/ui/Modal";
import { Spinner } from "@/components/ui/Spinner";
import { useVisorColors } from "@/lib/hooks/useVisorColors";
import { getImageUrl } from "@/lib/utils";
import type { VisorColor } from "@/lib/types";

interface VisorColorsModalProps {
  open: boolean;
  onClose: () => void;
  /** Visores específicos del producto. Si vienen vacíos/undefined se usa el catálogo global. */
  productVisorColors?: VisorColor[];
}

export function VisorColorsModal({ open, onClose, productVisorColors }: VisorColorsModalProps) {
  const hasProductColors = !!productVisorColors && productVisorColors.length > 0;
  const { data: globalColors, isLoading } = useVisorColors(open && !hasProductColors);
  const [selectedVisor, setSelectedVisor] = useState<VisorColor | null>(null);

  // Vuelve al grid cada vez que se abre/cierra el modal, para no arrastrar
  // la selección de una apertura anterior.
  useEffect(() => {
    if (!open) setSelectedVisor(null);
  }, [open]);

  const colors = hasProductColors ? productVisorColors! : (globalColors ?? []);

  return (
    <Modal open={open} onClose={onClose} title="Elige el color de tu visor">
      {!hasProductColors && isLoading ? (
        <div className="flex items-center justify-center py-8">
          <Spinner size={28} />
        </div>
      ) : colors.length === 0 ? (
        <p className="py-8 text-center text-sm text-brand-muted">No hay visores disponibles.</p>
      ) : selectedVisor ? (
        <div className="flex flex-col items-center gap-4">
          <button
            onClick={() => setSelectedVisor(null)}
            className="mr-auto flex items-center gap-1 text-sm font-medium text-brand-muted transition hover:text-brand-white"
          >
            <ArrowLeft size={16} /> Volver
          </button>
          {selectedVisor.image_url ? (
            <div className="relative h-56 w-56 overflow-hidden rounded-2xl border-2 border-white/20">
              <Image src={getImageUrl(selectedVisor.image_url)} alt={selectedVisor.name} fill className="object-cover" sizes="224px" />
            </div>
          ) : (
            <span
              className="h-56 w-56 rounded-2xl border-2 border-white/20"
              style={{ backgroundColor: selectedVisor.hex_color }}
              aria-hidden
            />
          )}
          <span className="text-center text-sm font-semibold text-brand-white">{selectedVisor.name}</span>
        </div>
      ) : (
        <div className="grid grid-cols-3 gap-4 sm:grid-cols-5">
          {colors.map((c) => (
            <button
              key={c.id}
              onClick={() => setSelectedVisor(c)}
              className="flex flex-col items-center gap-2"
            >
              {c.image_url ? (
                <div className="relative h-12 w-12 overflow-hidden rounded-full border-2 border-white/20 transition hover:border-brand-red">
                  <Image src={getImageUrl(c.image_url)} alt={c.name} fill className="object-cover" sizes="48px" />
                </div>
              ) : (
                <span
                  className="h-12 w-12 rounded-full border-2 border-white/20 transition hover:border-brand-red"
                  style={{ backgroundColor: c.hex_color }}
                  aria-hidden
                />
              )}
              <span className="text-center text-xs font-medium text-brand-white">{c.name}</span>
            </button>
          ))}
        </div>
      )}
    </Modal>
  );
}

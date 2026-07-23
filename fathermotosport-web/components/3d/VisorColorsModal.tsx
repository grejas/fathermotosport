"use client";

import { Modal } from "@/components/ui/Modal";
import { visorColors } from "./Materials";

interface VisorColorsModalProps {
  open: boolean;
  onClose: () => void;
}

export function VisorColorsModal({ open, onClose }: VisorColorsModalProps) {
  return (
    <Modal open={open} onClose={onClose} title="Elige el color de tu visor">
      <div className="grid grid-cols-3 gap-4 sm:grid-cols-5">
        {visorColors.map((c) => (
          <div key={c.id} className="flex flex-col items-center gap-2">
            <span
              className="h-12 w-12 rounded-full border-2 border-white/20"
              style={{ backgroundColor: c.hex }}
              aria-hidden
            />
            <span className="text-center text-xs font-medium text-brand-white">{c.label}</span>
          </div>
        ))}
      </div>
    </Modal>
  );
}

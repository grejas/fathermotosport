"use client";

import { useEffect, useId, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { ChevronDown } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { cn } from "@/lib/utils";
import { categoryHref, categoryLabel, type MenuCategory } from "@/lib/api/categories";

/**
 * Desplegable "Todas las categorías" del navbar de escritorio (patrón disclosure):
 * se abre con clic, Enter o Espacio; se cierra con Escape (devolviendo el foco al botón),
 * con un clic fuera o al salir con Tab. El panel crece en columnas y, si no alcanza el
 * alto, hace scroll por dentro.
 */
export function CategoriesDropdown({ categories }: { categories: MenuCategory[] }) {
  const t = useTranslations("nav");
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const rootRef = useRef<HTMLLIElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  // Abierto con teclado: el foco pasa al primer enlace. Con el mouse se queda en el botón.
  const focusFirst = useRef(false);

  useEffect(() => {
    if (!open) return;

    if (focusFirst.current) {
      panelRef.current?.querySelector<HTMLAnchorElement>("a")?.focus();
      focusFirst.current = false;
    }

    const onPointerDown = (e: PointerEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
    };
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("pointerdown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  if (categories.length === 0) return null;

  return (
    <li
      ref={rootRef}
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setOpen(false);
      }}
    >
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={(e) => {
          // detail === 0: activado con Enter/Espacio, no con el mouse.
          focusFirst.current = !open && e.detail === 0;
          setOpen((v) => !v);
        }}
        className={cn(
          "flex items-center gap-1 whitespace-nowrap text-sm font-medium transition hover:text-brand-red",
          open ? "text-brand-red" : "text-brand-white/80"
        )}
      >
        {t("all_categories")}
        <ChevronDown size={16} className={cn("transition-transform duration-200", open && "rotate-180")} />
      </button>

      <div
        id={panelId}
        ref={panelRef}
        hidden={!open}
        className="absolute inset-x-4 top-full mt-2 sm:inset-x-6"
      >
        {/* Borde fino en el rojo de marca, parejo en los cuatro lados. */}
        <div className="mx-auto max-h-[min(70vh,32rem)] max-w-5xl overflow-y-auto overscroll-contain rounded-2xl border border-brand-red/40 bg-brand-carbon/95 p-4 shadow-2xl backdrop-blur-md">
          <ul className="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-1">
            {categories.map((c) => (
              <li key={c.id}>
                <Link
                  href={categoryHref(c)}
                  onClick={() => setOpen(false)}
                  title={categoryLabel(t, c)}
                  className={cn(
                    // Hover: fondo rojo muy suave, texto blanco y barra roja de 2 px a la izquierda
                    // (sombra interior, no mueve el texto). El foco de teclado suma un anillo rojo.
                    "block truncate rounded-lg px-3 py-2 text-sm text-brand-white/90 outline-none transition",
                    "hover:bg-brand-red/[0.07] hover:text-white hover:shadow-[inset_2px_0_0_#E8001D]",
                    "focus-visible:bg-brand-red/[0.07] focus-visible:text-white focus-visible:shadow-[inset_2px_0_0_#E8001D] focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-brand-red",
                    c.depth > 0 && "pl-6 text-brand-white/70"
                  )}
                >
                  {categoryLabel(t, c)}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </li>
  );
}

/** La misma lista dentro del menú móvil, como acordeón. */
export function CategoriesAccordion({
  categories,
  onNavigate,
}: {
  categories: MenuCategory[];
  onNavigate: () => void;
}) {
  const t = useTranslations("nav");
  const [open, setOpen] = useState(false);
  const panelId = useId();

  if (categories.length === 0) return null;

  return (
    <li>
      <button
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-brand-white/90 hover:bg-white/10"
      >
        {t("all_categories")}
        <ChevronDown size={18} className={cn("transition-transform duration-200", open && "rotate-180")} />
      </button>
      {/* Sin el atributo hidden: la clase grid le ganaría a [hidden] del preflight. */}
      {open && (
        <ul id={panelId} className="mt-1 grid grid-cols-2 gap-1 border-l border-white/10 pl-2 sm:grid-cols-3">
          {categories.map((c) => (
            <li key={c.id}>
              <Link
                href={categoryHref(c)}
                onClick={onNavigate}
                title={categoryLabel(t, c)}
                className={cn(
                  "block truncate rounded-lg px-3 py-2 text-sm text-brand-white/80 hover:bg-white/10",
                  c.depth > 0 && "pl-5 text-brand-white/60"
                )}
              >
                {categoryLabel(t, c)}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </li>
  );
}

"use client";

import { motion } from "framer-motion";
import { useTranslations } from "next-intl";
import { HardHat, Hand, Footprints, Glasses, Shirt, Wrench, Package, type LucideIcon } from "lucide-react";
import { stagger, slideUp } from "@/animations/variants";
import { Link } from "@/lib/i18n/navigation";
import { categoryHref, categoryLabel, type MenuCategory } from "@/lib/api/categories";

const COLOR = "#fa0536";

/** Campo icon de la categoría en el panel → icono. Uno desconocido o vacío usa Package. */
const ICONS: Record<string, LucideIcon> = {
  helmet: HardHat,
  gloves: Hand,
  boots: Footprints,
  visor: Glasses,
  jacket: Shirt,
  shirt: Shirt,
  parts: Wrench,
  accessories: Package,
};

/** categories: las activas de la API; si vino vacío (la API falló), la sección no se muestra. */
export function CategoryGrid({ categories }: { categories: MenuCategory[] }) {
  const t = useTranslations("home");
  const tNav = useTranslations("nav");

  if (categories.length === 0) return null;

  return (
    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
      <h2 className="mb-8 text-2xl font-bold text-brand-white">{t("explore_by_category")}</h2>
      <motion.div
        variants={stagger}
        initial="hidden"
        whileInView="visible"
        viewport={{ once: true, margin: "-80px" }}
        className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6"
      >
        {categories.map((c) => {
          const Icon = (c.icon && ICONS[c.icon]) || Package;
          return (
            <motion.div key={c.id} variants={slideUp}>
              <Link
                href={categoryHref(c)}
                className="group flex flex-col items-center gap-3 rounded-2xl border border-white/[0.06] bg-brand-card p-6 transition-all duration-300 hover:-translate-y-1"
                style={{ ["--c" as string]: COLOR }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.borderColor = `${COLOR}66`;
                  e.currentTarget.style.boxShadow = `0 0 24px ${COLOR}33`;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.borderColor = "";
                  e.currentTarget.style.boxShadow = "";
                }}
              >
                <span
                  className="flex h-14 w-14 items-center justify-center rounded-xl"
                  style={{ backgroundColor: `${COLOR}1a`, color: COLOR }}
                >
                  <Icon size={26} />
                </span>
                <span className="text-center text-sm font-semibold text-brand-white">{categoryLabel(tNav, c)}</span>
              </Link>
            </motion.div>
          );
        })}
      </motion.div>
    </section>
  );
}

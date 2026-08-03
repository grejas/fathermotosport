"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { useTranslations } from "next-intl";
import { HardHat, Hand, Footprints, Shirt, Wrench, Package } from "lucide-react";
import { stagger, slideUp } from "@/animations/variants";

const categories = [
  { id: 1, key: "helmets", color: "#fa0536", icon: HardHat },
  { id: 2, key: "gloves", color: "#fa0536", icon: Hand },
  { id: 3, key: "boots", color: "#fa0536", icon: Footprints },
  { id: 5, key: "jackets", color: "#fa0536", icon: Shirt },
  { id: 6, key: "parts", color: "#fa0536", icon: Wrench },
  { id: 7, key: "accessories", color: "#fa0536", icon: Package },
] as const;

export function CategoryGrid() {
  const t = useTranslations("home");
  const tNav = useTranslations("nav");

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
          const Icon = c.icon;
          return (
            <motion.div key={c.id} variants={slideUp}>
              <Link
                href={`/catalog?category=${c.id}`}
                className="group flex flex-col items-center gap-3 rounded-2xl border border-white/[0.06] bg-brand-card p-6 transition-all duration-300 hover:-translate-y-1"
                style={{ ["--c" as string]: c.color }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.borderColor = `${c.color}66`;
                  e.currentTarget.style.boxShadow = `0 0 24px ${c.color}33`;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.borderColor = "";
                  e.currentTarget.style.boxShadow = "";
                }}
              >
                <span
                  className="flex h-14 w-14 items-center justify-center rounded-xl"
                  style={{ backgroundColor: `${c.color}1a`, color: c.color }}
                >
                  <Icon size={26} />
                </span>
                <span className="text-sm font-semibold text-brand-white">{tNav(c.key)}</span>
              </Link>
            </motion.div>
          );
        })}
      </motion.div>
    </section>
  );
}

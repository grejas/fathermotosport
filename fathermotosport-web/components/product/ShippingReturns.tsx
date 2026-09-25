"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { AnimatePresence, motion } from "framer-motion";
import { ArrowRight, ChevronDown, RotateCcw, Truck } from "lucide-react";
import { Link } from "@/lib/i18n/navigation";
import { cn } from "@/lib/utils";

export function ShippingReturns() {
  const t = useTranslations("shipping_returns");
  const [open, setOpen] = useState(false);

  return (
    <div className="rounded-xl border border-white/10 bg-brand-card">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
      >
        <div className="space-y-2">
          <p className="flex items-center gap-2 text-sm font-semibold text-brand-white">
            <Truck size={16} className="shrink-0 text-cat-boots" />
            {t("free_shipping")}
          </p>
          <p className="flex items-center gap-2 text-sm font-semibold text-brand-white">
            <RotateCcw size={16} className="shrink-0 text-cat-boots" />
            {t("free_returns")}
          </p>
        </div>
        <ChevronDown
          size={18}
          className={cn("shrink-0 text-brand-muted transition-transform", open && "rotate-180")}
        />
      </button>

      <AnimatePresence initial={false}>
        {open && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="overflow-hidden"
          >
            <div className="space-y-3 border-t border-white/10 px-4 py-3 text-sm text-brand-muted">
              <p>{t("summary_shipping")}</p>
              <p>{t("summary_damaged")}</p>
              <p>{t("summary_withdrawal")}</p>
              <Link
                href="/returns-policy"
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-red transition hover:brightness-125"
              >
                {t("full_policy_link")}
                <ArrowRight size={14} />
              </Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

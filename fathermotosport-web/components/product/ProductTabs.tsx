"use client";

import { useState } from "react";
import type { Product } from "@/lib/types";
import { cn } from "@/lib/utils";
import { ProductDescription } from "./ProductDescription";

type Tab = "description" | "specs";

export function ProductTabs({ product }: { product: Product }) {
  const [tab, setTab] = useState<Tab>("description");
  const specs = product.specs ?? {};

  const tabs: { id: Tab; label: string }[] = [
    { id: "description", label: "Descripción" },
    { id: "specs", label: "Especificaciones" },
  ];

  return (
    <div className="mt-12">
      <div className="flex gap-1 border-b border-white/10">
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={cn(
              "px-4 py-3 text-sm font-semibold transition",
              tab === t.id
                ? "border-b-2 border-brand-red text-brand-white"
                : "text-brand-muted hover:text-brand-white"
            )}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="py-6">
        {tab === "description" && <ProductDescription html={product.description} />}

        {tab === "specs" && (
          <div className="overflow-hidden rounded-xl border border-white/10">
            {Object.keys(specs).length === 0 ? (
              <p className="p-4 text-brand-muted">Sin especificaciones registradas.</p>
            ) : (
              <table className="w-full text-sm">
                <tbody>
                  {Object.entries(specs).map(([key, value], i) => (
                    <tr key={key} className={i % 2 ? "bg-white/[0.02]" : ""}>
                      <td className="w-1/3 px-4 py-3 font-medium capitalize text-brand-white">{key}</td>
                      <td className="px-4 py-3 text-brand-muted">{String(value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

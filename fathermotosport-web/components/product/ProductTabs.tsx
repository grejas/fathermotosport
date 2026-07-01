"use client";

import { useState } from "react";
import { Star } from "lucide-react";
import type { Product } from "@/lib/types";
import { cn, formatDate } from "@/lib/utils";

type Tab = "description" | "specs" | "reviews";

export function ProductTabs({ product }: { product: Product }) {
  const [tab, setTab] = useState<Tab>("description");
  const specs = product.specs ?? {};
  const reviews = product.reviews ?? [];

  const tabs: { id: Tab; label: string }[] = [
    { id: "description", label: "Descripción" },
    { id: "specs", label: "Especificaciones" },
    { id: "reviews", label: `Reseñas (${reviews.length})` },
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
        {tab === "description" && (
          <p className="whitespace-pre-line leading-relaxed text-brand-muted">
            {product.description ?? "Sin descripción disponible."}
          </p>
        )}

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

        {tab === "reviews" && (
          <div className="space-y-4">
            {reviews.length === 0 ? (
              <p className="text-brand-muted">Este producto aún no tiene reseñas.</p>
            ) : (
              reviews.map((r) => (
                <div key={r.id} className="rounded-xl border border-white/10 bg-brand-card p-4">
                  <div className="mb-1 flex items-center justify-between">
                    <span className="text-sm font-semibold text-brand-white">{r.user?.name ?? "Cliente"}</span>
                    <div className="flex">
                      {Array.from({ length: 5 }).map((_, i) => (
                        <Star
                          key={i}
                          size={14}
                          className={i < r.rating ? "fill-brand-gold text-brand-gold" : "text-brand-muted"}
                        />
                      ))}
                    </div>
                  </div>
                  {r.title && <p className="text-sm font-medium text-brand-white">{r.title}</p>}
                  {r.comment && <p className="mt-1 text-sm text-brand-muted">{r.comment}</p>}
                  <p className="mt-2 text-xs text-brand-muted">{formatDate(r.created_at)}</p>
                </div>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  );
}

"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import type { Brand } from "@/lib/types";
import { Button } from "@/components/ui/Button";

export interface CatalogFilterState {
  type: string;
  brands: number[];
  minPrice: string;
  maxPrice: string;
  certifications: string[];
}

const types = [
  { value: "todos", key: "all" },
  { value: "integral", key: "integral" },
  { value: "modular", key: "modular" },
  { value: "off-road", key: "off_road" },
  { value: "adventure", key: "adventure" },
] as const;
const certs = [
  { value: "ECE 22.06", key: "ece" },
  { value: "DOT", key: "dot" },
] as const;

interface Props {
  brands: Brand[];
  value: CatalogFilterState;
  onChange: (next: CatalogFilterState) => void;
  onClear: () => void;
}

export function CatalogFilters({ brands, value, onChange, onClear }: Props) {
  const t = useTranslations("catalog");
  const [local, setLocal] = useState<CatalogFilterState>(value);

  useEffect(() => setLocal(value), [value]);

  const apply = (next: Partial<CatalogFilterState>) => {
    const merged = { ...local, ...next };
    setLocal(merged);
    onChange(merged);
  };

  const toggleBrand = (id: number) =>
    apply({
      brands: local.brands.includes(id)
        ? local.brands.filter((b) => b !== id)
        : [...local.brands, id],
    });

  const toggleCert = (c: string) =>
    apply({
      certifications: local.certifications.includes(c)
        ? local.certifications.filter((x) => x !== c)
        : [...local.certifications, c],
    });

  return (
    <aside className="w-full shrink-0 space-y-6 lg:w-[220px]">
      <div>
        <h4 className="mb-2 text-sm font-bold text-brand-white">{t("type")}</h4>
        <div className="flex flex-col gap-1">
          {types.map((type) => (
            <button
              key={type.value}
              onClick={() => apply({ type: type.value })}
              className={`rounded-lg px-2 py-1.5 text-left text-sm capitalize transition ${
                local.type === type.value
                  ? "bg-brand-red/15 text-brand-red"
                  : "text-brand-muted hover:text-brand-white"
              }`}
            >
              {t(type.key)}
            </button>
          ))}
        </div>
      </div>

      <div>
        <h4 className="mb-2 text-sm font-bold text-brand-white">{t("brands")}</h4>
        <div className="flex max-h-48 flex-col gap-1.5 overflow-y-auto">
          {brands.map((b) => (
            <label key={b.id} className="flex items-center gap-2 text-sm text-brand-muted">
              <input
                type="checkbox"
                checked={local.brands.includes(b.id)}
                onChange={() => toggleBrand(b.id)}
                className="accent-brand-red"
              />
              {b.name}
            </label>
          ))}
        </div>
      </div>

      <div>
        <h4 className="mb-2 text-sm font-bold text-brand-white">{t("price")}</h4>
        <div className="flex items-center gap-2">
          <input
            type="number"
            placeholder="Min"
            value={local.minPrice}
            onChange={(e) => apply({ minPrice: e.target.value })}
            className="input-brand px-2 py-1.5 text-sm"
          />
          <span className="text-brand-muted">—</span>
          <input
            type="number"
            placeholder="Max"
            value={local.maxPrice}
            onChange={(e) => apply({ maxPrice: e.target.value })}
            className="input-brand px-2 py-1.5 text-sm"
          />
        </div>
      </div>

      <div>
        <h4 className="mb-2 text-sm font-bold text-brand-white">{t("certification")}</h4>
        <div className="flex flex-col gap-1.5">
          {certs.map((c) => (
            <label key={c.value} className="flex items-center gap-2 text-sm text-brand-muted">
              <input
                type="checkbox"
                checked={local.certifications.includes(c.value)}
                onChange={() => toggleCert(c.value)}
                className="accent-brand-red"
              />
              {t(c.key)}
            </label>
          ))}
        </div>
      </div>

      <Button variant="glass" size="sm" className="w-full" onClick={onClear}>
        {t("clear_filters")}
      </Button>
    </aside>
  );
}

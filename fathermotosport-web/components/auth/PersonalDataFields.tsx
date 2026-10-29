"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { Country } from "country-state-city";
import { Input } from "@/components/ui/Input";
import type { FieldErrors } from "@/lib/validators";
import { MIN_BIRTH_DATE, phoneWithCountryPrefix, todayLocal } from "@/lib/personalData";

export interface PersonalDataValues {
  country: string;
  phone: string;
  birth_date: string;
  adult_confirmed: boolean;
}

/**
 * País, teléfono con código de país, fecha de nacimiento opcional y, SOLO si la fecha
 * está vacía, la casilla "Tengo 18 años o más". Lo usan el registro y "Completa tus datos".
 * section: "required" pinta país y teléfono; "optional", la fecha y la casilla.
 */
export function PersonalDataFields({
  values,
  errors,
  onChange,
  section,
}: {
  values: PersonalDataValues;
  errors: FieldErrors;
  onChange: (next: PersonalDataValues) => void;
  section: "required" | "optional";
}) {
  const t = useTranslations("auth");
  const tCheckout = useTranslations("checkout");
  const countries = useMemo(
    () => [...Country.getAllCountries()].sort((a, b) => a.name.localeCompare(b.name)),
    []
  );

  if (section === "required") {
    return (
      <>
        <div>
          <label htmlFor="country" className="mb-1.5 block text-sm font-medium text-brand-white">
            {t("country")}
          </label>
          <select
            id="country"
            name="country"
            value={values.country}
            onChange={(e) =>
              onChange({
                ...values,
                country: e.target.value,
                phone: phoneWithCountryPrefix(values.phone, values.country, e.target.value),
              })
            }
            className="input-brand"
          >
            <option value="" disabled>
              {t("country_placeholder")}
            </option>
            {countries.map((c) => (
              <option key={c.isoCode} value={c.isoCode}>
                {c.flag} {c.name}
              </option>
            ))}
          </select>
          {errors.country && <p className="mt-1.5 text-xs text-brand-red">{errors.country}</p>}
        </div>
        <Input
          label={tCheckout("phone")}
          name="phone"
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          value={values.phone}
          onChange={(e) => onChange({ ...values, phone: e.target.value })}
          error={errors.phone}
          hint={errors.phone ? undefined : t("phone_hint")}
          placeholder="+591 76543210"
          maxLength={25}
        />
      </>
    );
  }

  return (
    <div className="space-y-3">
      <Input
        label={t("birth_date")}
        type="date"
        name="birth_date"
        value={values.birth_date}
        onChange={(e) => onChange({ ...values, birth_date: e.target.value })}
        error={errors.birth_date}
        hint={errors.birth_date ? undefined : t("birth_date_hint")}
        min={MIN_BIRTH_DATE}
        max={todayLocal()}
      />
      {!values.birth_date && (
        <div>
          <label className="flex cursor-pointer items-start gap-2 text-sm text-brand-white">
            <input
              type="checkbox"
              name="adult_confirmed"
              checked={values.adult_confirmed}
              onChange={(e) => onChange({ ...values, adult_confirmed: e.target.checked })}
              className="mt-0.5 h-4 w-4 shrink-0 accent-brand-red"
            />
            {t("adult_label")}
          </label>
          {errors.adult_confirmed && (
            <p className="mt-1.5 text-xs text-brand-red">{errors.adult_confirmed}</p>
          )}
        </div>
      )}
    </div>
  );
}

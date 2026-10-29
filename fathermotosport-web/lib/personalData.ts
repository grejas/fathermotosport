import { Country } from "country-state-city";
import type { FieldErrors } from "@/lib/validators";

/**
 * Teléfono, país, fecha de nacimiento y casilla de 18 años: mismas reglas que la API
 * (DatosPersonales) en el registro y en "Completa tus datos".
 */

export const MIN_BIRTH_DATE = "1926-01-01";

/** Hoy como AAAA-MM-DD con la fecha LOCAL del navegador (toISOString usaría UTC). */
export function todayLocal(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Años cumplidos a una fecha (cumplirlos ese día cuenta). */
export function ageOn(birth: string, today: string = todayLocal()): number {
  const [by, bm, bd] = birth.split("-").map(Number);
  const [ty, tm, td] = today.split("-").map(Number);
  return ty - by - (tm < bm || (tm === bm && td < bd) ? 1 : 0);
}

/** Prefijo telefónico de un país, solo dígitos ("BO" => "591"). "" si no se conoce. */
export function dialCode(iso: string): string {
  const raw = Country.getCountryByCode(iso)?.phonecode ?? "";
  return raw.split(/\s*and\s*/i)[0].replace(/\D/g, "");
}

/** "+591 7654-3210" => "+59176543210"; null si no es + y 8 a 15 dígitos. */
export function normalizePhone(value: string): string | null {
  const v = value.trim();
  if (!/^\+[\d\s\-().]+$/.test(v)) return null;
  const digits = v.replace(/\D/g, "");
  return /^[1-9]\d{7,14}$/.test(digits) ? `+${digits}` : null;
}

/**
 * Al elegir un país, el teléfono arranca con su prefijo. Solo si está vacío o si todavía
 * tiene únicamente el prefijo del país anterior: nunca pisa un número ya escrito.
 */
export function phoneWithCountryPrefix(phone: string, previousIso: string, nextIso: string): string {
  const prev = dialCode(previousIso);
  const onlyPrefix = phone.trim() === "" || (prev !== "" && phone.replace(/\D/g, "") === prev);
  const next = dialCode(nextIso);
  return onlyPrefix && next ? `+${next} ` : phone;
}

type Translate = (key: string) => string;

/** Errores por campo, con textos de messages (namespace auth). */
export function validatePersonalData(
  data: { phone: string; country: string; birth_date: string; adult_confirmed: boolean },
  t: Translate
): FieldErrors {
  const errors: FieldErrors = {};

  if (!data.country) errors.country = t("country_required_error");

  // Vacío, o solo el prefijo que se puso al elegir el país: falta el número.
  const onlyPrefix = data.phone.replace(/\D/g, "") === dialCode(data.country);
  if (!data.phone.trim() || onlyPrefix) {
    errors.phone = t("phone_required_error");
  } else if (!normalizePhone(data.phone)) {
    errors.phone = t("phone_invalid_error");
  }

  if (data.birth_date) {
    const today = todayLocal();
    if (data.birth_date > today) errors.birth_date = t("birth_future_error");
    else if (data.birth_date < MIN_BIRTH_DATE) errors.birth_date = t("birth_invalid_error");
    else if (ageOn(data.birth_date, today) < 18) errors.birth_date = t("underage_error");
  } else if (!data.adult_confirmed) {
    errors.adult_confirmed = t("adult_required_error");
  }

  return errors;
}

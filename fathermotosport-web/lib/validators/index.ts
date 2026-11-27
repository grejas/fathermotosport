// Validadores ligeros sin dependencias externas.

export const isEmail = (value: string): boolean =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

export const minLength = (value: string, length: number): boolean =>
  value.trim().length >= length;

export interface PasswordRequirement {
  key: "length" | "uppercase" | "lowercase" | "number" | "symbol";
  met: boolean;
}

const PASSWORD_RULES: { key: PasswordRequirement["key"]; test: (v: string) => boolean }[] = [
  { key: "length", test: (v) => v.length >= 8 },
  { key: "uppercase", test: (v) => /[A-Z]/.test(v) },
  { key: "lowercase", test: (v) => /[a-z]/.test(v) },
  { key: "number", test: (v) => /\d/.test(v) },
  { key: "symbol", test: (v) => /[^a-zA-Z0-9]/.test(v) },
];

export const getPasswordRequirements = (password: string): PasswordRequirement[] =>
  PASSWORD_RULES.map((rule) => ({ key: rule.key, met: rule.test(password) }));

export const isStrongPassword = (password: string): boolean =>
  PASSWORD_RULES.every((rule) => rule.test(password));

export interface FieldErrors {
  [key: string]: string | undefined;
}

export function validateLogin(data: { email: string; password: string }): FieldErrors {
  const errors: FieldErrors = {};
  if (!isEmail(data.email)) errors.email = "Ingresa un email válido.";
  if (!minLength(data.password, 1)) errors.password = "La contraseña es obligatoria.";
  return errors;
}

const NAME_REGEX = /^[a-zA-ZÀ-ÿ\s]+$/;
const PHONE_REGEX = /^[+\d\s\-()]+$/;

const isValidName = (value: string): boolean => {
  const v = value.trim();
  return v.length > 0 && v.length <= 25 && NAME_REGEX.test(v);
};

const isValidPhone = (value: string): boolean => {
  const v = value.trim();
  if (!PHONE_REGEX.test(v)) return false;
  const digitCount = (v.match(/\d/g) ?? []).length;
  return digitCount >= 7 && digitCount <= 15;
};

const MIN_BIRTH_DATE = "1926-01-01";

const isValidBirthDate = (value: string): boolean => {
  const today = new Date().toISOString().slice(0, 10);
  return value >= MIN_BIRTH_DATE && value <= today;
};

export function validateRegister(data: {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  birth_date?: string;
  password: string;
  password_confirmation: string;
}): FieldErrors {
  const errors: FieldErrors = {};
  if (!isEmail(data.email)) errors.email = "Ingresa un email válido.";
  if (!isStrongPassword(data.password)) errors.password = "La contraseña no cumple los requisitos.";
  if (data.password !== data.password_confirmation)
    errors.password_confirmation = "Las contraseñas no coinciden.";
  if (!isValidName(data.first_name))
    errors.first_name = "El nombre solo puede contener letras (máx. 25 caracteres).";
  if (!isValidName(data.last_name))
    errors.last_name = "El apellido solo puede contener letras (máx. 25 caracteres).";
  if (data.phone && data.phone.trim() && !isValidPhone(data.phone))
    errors.phone = "El teléfono debe tener entre 7 y 15 dígitos.";
  if (data.birth_date && !isValidBirthDate(data.birth_date))
    errors.birth_date = "La fecha debe estar entre 1926 y hoy.";
  return errors;
}

/**
 * Países donde el código postal es obligatorio para entregar. En el resto (Bolivia,
 * Centroamérica norte, Panamá, Paraguay, Venezuela, Rep. Dominicana) es opcional.
 */
const CP_OBLIGATORIO = new Set([
  "US", "CA", "MX", "AR", "CL", "CO", "PE", "UY", "EC", "CR",
  "ES", "PT", "FR", "IT", "DE", "GB", "BE", "NL", "CH", "AT",
  "SE", "NO", "DK", "FI", "IE", "PL", "CZ", "HU", "RO", "GR",
]);

export function codigoPostalObligatorio(country: string): boolean {
  return CP_OBLIGATORIO.has(country.toUpperCase());
}

/** Formato del código postal: estricto en EE.UU. y Canadá, flexible en el resto. */
function codigoPostalValido(country: string, cp: string): boolean {
  const v = cp.trim();
  if (country === "US") return /^\d{5}(-\d{4})?$/.test(v);
  if (country === "CA") return /^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/.test(v);
  return /^[A-Za-z0-9][A-Za-z0-9 -]{1,9}$/.test(v);
}

/**
 * Errores por campo (no un único "full_name"), para poder señalar exactamente
 * cuál falta en el formulario. email undefined = el cliente tiene sesión iniciada
 * y su email sale de la cuenta. `t` traduce las claves del namespace checkout.
 */
export function validateCheckout(
  data: {
    first_name: string;
    last_name?: string;
    email?: string;
    phone: string;
    country: string;
    city: string;
    address_line: string;
    postal_code?: string;
  },
  t: (key: string) => string
): FieldErrors {
  const errors: FieldErrors = {};
  const cp = data.postal_code ?? "";
  if (!minLength(data.first_name, 1)) errors.first_name = t("err_first_name");
  if (data.email !== undefined && !isEmail(data.email)) errors.email = t("err_email");
  if (!minLength(data.address_line, 1)) errors.address_line = t("err_address");
  if (!minLength(data.city, 1)) errors.city = t("err_city");
  if (codigoPostalObligatorio(data.country) && !minLength(cp, 1)) errors.postal_code = t("err_postal_required");
  else if (minLength(cp, 1) && !codigoPostalValido(data.country, cp)) errors.postal_code = t("err_postal_invalid");
  if (!minLength(data.country, 1)) errors.country = t("err_country");
  // Solo el prefijo ("+591 ") no cuenta como teléfono.
  if (data.phone.replace(/\D/g, "").length < 7) errors.phone = t("err_phone");
  return errors;
}

export const hasErrors = (errors: FieldErrors): boolean =>
  Object.values(errors).some(Boolean);

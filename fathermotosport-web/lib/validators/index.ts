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

export function validateCheckout(data: {
  full_name: string;
  email?: string;
  phone: string;
  country: string;
  city: string;
  address_line: string;
}): FieldErrors {
  const errors: FieldErrors = {};
  if (!minLength(data.full_name, 1)) errors.full_name = "Nombre obligatorio.";
  if (data.email !== undefined && !isEmail(data.email)) errors.email = "Email válido obligatorio.";
  if (!minLength(data.phone, 1)) errors.phone = "Teléfono obligatorio.";
  if (!minLength(data.country, 1)) errors.country = "Selecciona un país.";
  if (!minLength(data.city, 1)) errors.city = "Ciudad obligatoria.";
  if (!minLength(data.address_line, 1)) errors.address_line = "Dirección obligatoria.";
  return errors;
}

export const hasErrors = (errors: FieldErrors): boolean =>
  Object.values(errors).some(Boolean);

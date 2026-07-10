// Validadores ligeros sin dependencias externas.

export const isEmail = (value: string): boolean =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

export const minLength = (value: string, length: number): boolean =>
  value.trim().length >= length;

export interface FieldErrors {
  [key: string]: string | undefined;
}

export function validateLogin(data: { email: string; password: string }): FieldErrors {
  const errors: FieldErrors = {};
  if (!isEmail(data.email)) errors.email = "Ingresa un email válido.";
  if (!minLength(data.password, 1)) errors.password = "La contraseña es obligatoria.";
  return errors;
}

export function validateRegister(data: {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
}): FieldErrors {
  const errors: FieldErrors = {};
  // Nombre y apellido son opcionales; solo email y contraseña son obligatorios.
  if (!isEmail(data.email)) errors.email = "Ingresa un email válido.";
  if (!minLength(data.password, 8)) errors.password = "Mínimo 8 caracteres.";
  if (data.password !== data.password_confirmation)
    errors.password_confirmation = "Las contraseñas no coinciden.";
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

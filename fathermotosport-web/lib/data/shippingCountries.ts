/**
 * Países a los que se ofrece envío: lista fija y cerrada de 40 (20 de América y 20 de
 * Europa), definida a mano y no generada por ninguna librería.
 *
 * Se usa SOLO en el checkout. El registro de usuarios sigue mostrando la lista completa
 * del mundo, porque cualquiera puede crear una cuenta. La misma lista existe en el
 * backend (App\Support\ShippingCountries) para el formulario de Opciones de envío.
 */
export interface ShippingCountry {
  code: string;
  name: string;
}

export const AMERICAS_COUNTRIES: readonly ShippingCountry[] = [
  { code: "BO", name: "Bolivia" },
  { code: "AR", name: "Argentina" },
  { code: "BR", name: "Brasil" },
  { code: "CL", name: "Chile" },
  { code: "PE", name: "Perú" },
  { code: "PY", name: "Paraguay" },
  { code: "UY", name: "Uruguay" },
  { code: "EC", name: "Ecuador" },
  { code: "CO", name: "Colombia" },
  { code: "VE", name: "Venezuela" },
  { code: "MX", name: "México" },
  { code: "US", name: "Estados Unidos" },
  { code: "CA", name: "Canadá" },
  { code: "CR", name: "Costa Rica" },
  { code: "PA", name: "Panamá" },
  { code: "GT", name: "Guatemala" },
  { code: "SV", name: "El Salvador" },
  { code: "HN", name: "Honduras" },
  { code: "NI", name: "Nicaragua" },
  { code: "DO", name: "República Dominicana" },
];

export const EUROPE_COUNTRIES: readonly ShippingCountry[] = [
  { code: "ES", name: "España" },
  { code: "PT", name: "Portugal" },
  { code: "FR", name: "Francia" },
  { code: "IT", name: "Italia" },
  { code: "DE", name: "Alemania" },
  { code: "GB", name: "Reino Unido" },
  { code: "BE", name: "Bélgica" },
  { code: "NL", name: "Países Bajos" },
  { code: "CH", name: "Suiza" },
  { code: "AT", name: "Austria" },
  { code: "SE", name: "Suecia" },
  { code: "NO", name: "Noruega" },
  { code: "DK", name: "Dinamarca" },
  { code: "FI", name: "Finlandia" },
  { code: "IE", name: "Irlanda" },
  { code: "PL", name: "Polonia" },
  { code: "CZ", name: "República Checa" },
  { code: "HU", name: "Hungría" },
  { code: "RO", name: "Rumania" },
  { code: "GR", name: "Grecia" },
];

/** Los 40 destinos, ordenados por nombre para el selector. */
export const SHIPPING_COUNTRIES: readonly ShippingCountry[] = [
  ...AMERICAS_COUNTRIES,
  ...EUROPE_COUNTRIES,
].sort((a, b) => a.name.localeCompare(b.name, "es"));

const BY_CODE = new Map(SHIPPING_COUNTRIES.map((c) => [c.code, c]));

export function shipsToCountry(isoCode: string): boolean {
  return BY_CODE.has(isoCode.toUpperCase());
}

export function shippingCountryName(isoCode: string): string | null {
  return BY_CODE.get(isoCode.toUpperCase())?.name ?? null;
}

/** Bandera a partir del código ISO (BO → 🇧🇴), sin depender de ninguna librería. */
export function countryFlag(isoCode: string): string {
  return isoCode
    .toUpperCase()
    .replace(/[A-Z]/g, (char) => String.fromCodePoint(0x1f1e6 + char.charCodeAt(0) - 65));
}

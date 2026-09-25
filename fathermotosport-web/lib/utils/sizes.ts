// Orden canónico de tallas por letra (cascos, chamarras, guantes con talla en letra).
const LETTER_ORDER = ["XXXS", "XXS", "XS", "S", "M", "L", "XL", "XXL", "2XL", "XXXL", "3XL", "4XL", "5XL"];

// Grupo 0 = letras, 1 = numéricas ("8", "8.5", "57-58", "42 EU"), 2 = cualquier otra ("Único").
function sizeKey(size: string): [number, number, string] {
  const normalized = size.trim().toUpperCase();

  const letterIdx = LETTER_ORDER.indexOf(normalized);
  if (letterIdx !== -1) return [0, letterIdx, normalized];

  const num = normalized.match(/^\d+([.,]\d+)?/);
  if (num) return [1, parseFloat(num[0].replace(",", ".")), normalized];

  return [2, 0, normalized];
}

/** Ordena tallas: XS → XXL primero, luego numéricas ascendentes, luego el resto alfabético. */
export function sortSizes(sizes: string[]): string[] {
  return [...sizes].sort((a, b) => {
    const [ga, na, sa] = sizeKey(a);
    const [gb, nb, sb] = sizeKey(b);
    if (ga !== gb) return ga - gb;
    if (na !== nb) return na - nb;
    return sa.localeCompare(sb);
  });
}

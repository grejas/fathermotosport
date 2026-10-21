/**
 * Fotos de reseñas: se reducen en el navegador antes de subirlas (una foto de celular
 * de 4-8 MB queda en unos cientos de KB). El servidor igual las vuelve a codificar.
 */

export const TIPOS_DE_FOTO = ["image/jpeg", "image/png", "image/webp"] as const;

/** Lo que acepta el servidor por foto (5 MB). */
export const MAX_BYTES_DE_FOTO = 5 * 1024 * 1024;

/** Originales más grandes ni se intentan abrir: el navegador del celular podría colgarse. */
export const MAX_BYTES_ORIGINAL = 25 * 1024 * 1024;

export const LADO_MAXIMO = 1600;

const CALIDAD = 0.82;

async function dibujable(archivo: File): Promise<{ fuente: CanvasImageSource; ancho: number; alto: number; cerrar: () => void }> {
  // createImageBitmap respeta la orientación EXIF de las fotos de celular.
  if (typeof createImageBitmap === "function") {
    try {
      const bitmap = await createImageBitmap(archivo, { imageOrientation: "from-image" });
      return { fuente: bitmap, ancho: bitmap.width, alto: bitmap.height, cerrar: () => bitmap.close() };
    } catch {
      // Navegadores viejos: se prueba con <img>.
    }
  }

  const url = URL.createObjectURL(archivo);
  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    return { fuente: img, ancho: img.naturalWidth, alto: img.naturalHeight, cerrar: () => URL.revokeObjectURL(url) };
  } catch (error) {
    URL.revokeObjectURL(url);
    throw error;
  }
}

function aBlob(canvas: HTMLCanvasElement, tipo: string): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, tipo, CALIDAD));
}

/**
 * Devuelve la foto reducida a 1600 px como WebP (o JPEG si el navegador no sabe hacer
 * WebP, como Safari viejo). Si la reducida sale más pesada que el original, se queda
 * con el original. Lanza un error si el archivo no se puede leer como imagen.
 */
export async function comprimirImagen(archivo: File, indice: number): Promise<File> {
  const { fuente, ancho, alto, cerrar } = await dibujable(archivo);

  try {
    const escala = Math.min(1, LADO_MAXIMO / Math.max(ancho, alto));
    const canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.round(ancho * escala));
    canvas.height = Math.max(1, Math.round(alto * escala));
    const ctx = canvas.getContext("2d");
    if (!ctx) throw new Error("canvas");

    ctx.drawImage(fuente, 0, 0, canvas.width, canvas.height);
    let blob = await aBlob(canvas, "image/webp");

    if (!blob || blob.type !== "image/webp") {
      // JPEG no tiene transparencia: se apoya sobre blanco.
      ctx.globalCompositeOperation = "destination-over";
      ctx.fillStyle = "#fff";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      blob = await aBlob(canvas, "image/jpeg");
    }
    if (!blob) throw new Error("toBlob");

    if (blob.size >= archivo.size && escala === 1) return archivo;

    const extension = blob.type === "image/webp" ? "webp" : "jpg";
    return new File([blob], `foto-${indice + 1}.${extension}`, { type: blob.type });
  } finally {
    cerrar();
  }
}

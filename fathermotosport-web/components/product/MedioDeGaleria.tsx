"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { Play } from "lucide-react";
import type { ProductImage } from "@/lib/types";
import { BannerVideo } from "@/components/media/BannerVideo";
import { cn, getImageUrl } from "@/lib/utils";

/** El tipo de un elemento de la galería. Sin `type` (API anterior) es una imagen. */
export function tipoDeMedio(medio: ProductImage): "image" | "gif" | "video" {
  return medio.type ?? "image";
}

/** La primera imagen estática de la galería: lo que se ve si un video sin poster falla. */
export function primeraImagen(galeria: ProductImage[]): string | null {
  const imagen = galeria.find((m) => tipoDeMedio(m) === "image");
  return imagen ? getImageUrl(imagen.url) : null;
}

/** Nombre accesible de una miniatura: "Video 2 de 5". */
export function useEtiquetaDeMiniatura() {
  const t = useTranslations("gallery");
  return (medio: ProductImage, i: number, total: number) =>
    t(`item_${tipoDeMedio(medio)}`, { current: i + 1, total });
}

/**
 * El elemento grande de la galería: imagen, GIF (tal cual, animado) o video.
 *
 * El video es el de los banners (BannerVideo): arranca en silencio cuando entra en
 * pantalla, en bucle, se pausa fuera de ella, y con movimiento reducido o Save-Data
 * queda el poster con un botón de play. Si falla, el poster o la primera foto.
 * Quien lo usa le pone `key={medio.id}`: al cambiar de elemento el video se desmonta
 * y deja de descargarse.
 */
export function MedioPrincipal({
  medio,
  nombre,
  prioridad = false,
  respaldoUrl,
  claseImagen = "object-contain",
}: {
  medio: ProductImage;
  nombre: string;
  /** El primero de la galería es el LCP de la ficha. */
  prioridad?: boolean;
  respaldoUrl?: string | null;
  claseImagen?: string;
}) {
  const sizes = "(max-width:1024px) 100vw, 50vw";

  if (tipoDeMedio(medio) === "video") {
    return (
      <BannerVideo
        videoUrl={getImageUrl(medio.url)}
        posterUrl={medio.poster_url ? getImageUrl(medio.poster_url) : null}
        conSonido={Boolean(medio.with_sound)}
        etiqueta={nombre}
        modo="seccion"
        prioridad={prioridad}
        sizes={sizes}
        proporcion="h-full"
        ajuste="contain"
        respaldoUrl={respaldoUrl}
        className="rounded-none bg-brand-dark ring-0"
      />
    );
  }

  return (
    <Image
      src={getImageUrl(medio.url)}
      alt={nombre}
      fill
      sizes={sizes}
      // Un GIF se sirve tal cual, para que se mueva.
      unoptimized={tipoDeMedio(medio) === "gif"}
      className={claseImagen}
      priority={prioridad}
    />
  );
}

/**
 * Contenido de una miniatura. Nunca baja un video ni un GIF entero: el video muestra su
 * poster (o un recuadro oscuro) con un ícono de play; el GIF, su primer fotograma (o un
 * recuadro) con la marca "GIF".
 */
export function MiniaturaDeMedio({ medio }: { medio: ProductImage }) {
  const tipo = tipoDeMedio(medio);
  const estatica = tipo === "video" ? medio.poster_url : tipo === "gif" ? medio.thumbnail_url : medio.thumbnail_url ?? medio.url;

  return (
    <>
      {estatica ? (
        <Image src={getImageUrl(estatica)} alt="" fill className="object-cover" sizes="64px" />
      ) : (
        <span className="absolute inset-0 bg-gradient-to-br from-neutral-700 via-neutral-900 to-black" aria-hidden />
      )}
      {tipo === "video" && (
        <span className="absolute inset-0 flex items-center justify-center bg-black/30" aria-hidden>
          <span className="flex h-7 w-7 items-center justify-center rounded-full bg-black/70 text-white ring-1 ring-white/30">
            <Play size={13} className="translate-x-px" fill="currentColor" />
          </span>
        </span>
      )}
      {tipo === "gif" && (
        <span
          className={cn(
            "absolute rounded bg-black/75 px-1 text-[10px] font-bold leading-4 text-white",
            estatica ? "bottom-1 right-1" : "inset-0 m-auto h-4 w-fit"
          )}
          aria-hidden
        >
          GIF
        </span>
      )}
    </>
  );
}

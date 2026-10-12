"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Maximize, Minimize, Pause, Play, Volume2, VolumeX } from "lucide-react";
import { cn } from "@/lib/utils";

interface Props {
  videoUrl: string;
  /** Versión liviana para pantallas chicas; si no hay, se usa la principal. */
  videoMobileUrl?: string | null;
  /** Opcional. Sin poster: fondo oscuro, botón de play y el primer fotograma del video. */
  posterUrl?: string | null;
  /** Muestra el botón de sonido. El video arranca siempre en silencio. */
  conSonido?: boolean;
  /** Nombre accesible del video (el título del banner). */
  etiqueta: string;
  /**
   * hero: el video se monta apenas carga la página (después del poster) y arranca solo.
   * seccion: no se pide nada hasta que el video se acerca a la pantalla.
   */
  modo: "hero" | "seccion";
  /** El poster es el LCP de la página: se precarga con prioridad. */
  prioridad?: boolean;
  /** Sin controles el video es decorativo: queda fuera del árbol de accesibilidad. */
  controles?: boolean;
  /** Atributo sizes del poster (next/image). */
  sizes?: string;
  /**
   * Proporción del recuadro (clases aspect-*). La reserva el CSS desde el primer render,
   * así nada se mueve cuando llega el video (CLS 0). El video la llena con object-cover.
   */
  proporcion?: string;
  className?: string;
}

/** Pantalla chica: se usa la versión móvil del video. Coincide con el md de Tailwind. */
const CONSULTA_MOVIL = "(max-width: 767px)";

const formatoTiempo = (s: number) => {
  if (!Number.isFinite(s) || s < 0) return "0:00";
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const seg = Math.floor(s % 60).toString().padStart(2, "0");
  return h > 0 ? `${h}:${m.toString().padStart(2, "0")}:${seg}` : `${m}:${seg}`;
};

interface ConexionConAhorro {
  saveData?: boolean;
}

/**
 * Video de un banner, con su proporción reservada (16:9 salvo que se pida otra; sin
 * saltos de layout).
 *
 * Primero se ve el poster, con prioridad de carga; el <video> se monta después, así no
 * compite con el resto de la página. Con preload="metadata" el navegador pide el video
 * por rangos a medida que lo reproduce: uno largo nunca se descarga entero al cargar.
 *
 * Con prefers-reduced-motion, Save-Data o si el video falla, queda el poster con un
 * botón de reproducir: el video recién se pide si la persona lo elige. Se pausa solo
 * fuera de pantalla y con la pestaña oculta.
 *
 * Sin poster, el fondo es oscuro y el <video> se monta igual (preload="metadata" y
 * "#t=0.1" en la URL) para que el navegador pinte el primer fotograma debajo del botón
 * de play. Si el video falla (formato que el navegador no reproduce), queda el fondo
 * oscuro con el botón: la página sigue entera.
 */
export function BannerVideo({
  videoUrl,
  videoMobileUrl,
  posterUrl,
  conSonido = false,
  etiqueta,
  modo,
  prioridad = false,
  controles = true,
  sizes = "(min-width: 1024px) 50vw, 100vw",
  proporcion = "aspect-video",
  className,
}: Props) {
  const t = useTranslations("banners");
  const contenedorRef = useRef<HTMLDivElement>(null);
  const videoRef = useRef<HTMLVideoElement>(null);

  // ¿Puede arrancar solo? Se decide en el navegador (movimiento reducido, ahorro de datos).
  const [autoPermitido, setAutoPermitido] = useState<boolean | null>(null);
  const [src, setSrc] = useState<string | null>(null);
  // El <video> existe en el DOM (y con eso el navegador puede empezar a pedirlo).
  const [montado, setMontado] = useState(false);
  const [fallo, setFallo] = useState(false);
  // Tocó el play grande: el botón se quita aunque el video ya estuviera montado (sin poster).
  const [iniciado, setIniciado] = useState(false);
  const [reproduciendo, setReproduciendo] = useState(false);
  // Se pidió reproducir pero todavía no hay datos (o se está rellenando el búfer).
  const [cargando, setCargando] = useState(false);
  const [silenciado, setSilenciado] = useState(true);
  const [tiempo, setTiempo] = useState(0);
  const [duracion, setDuracion] = useState(0);
  const [pantallaCompleta, setPantallaCompleta] = useState(false);
  const [controlesTactiles, setControlesTactiles] = useState(false);

  const visibleRef = useRef(false);
  // Si la persona pausó, ni el scroll ni volver a la pestaña lo reanudan.
  const pausadoPorUsuario = useRef(false);
  // Pidió reproducir (botón de play sobre el poster): se respeta aunque no sea automático.
  const pidioReproducir = useRef(false);
  const ocultarTactil = useRef<number | null>(null);

  // 1. Al montar: qué archivo usar y si puede arrancar solo.
  useEffect(() => {
    const reducido = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const ahorro = Boolean((navigator as Navigator & { connection?: ConexionConAhorro }).connection?.saveData);
    const movil = window.matchMedia(CONSULTA_MOVIL).matches;
    const archivo = movil && videoMobileUrl ? videoMobileUrl : videoUrl;
    // Sin poster, "#t=0.1" hace que el navegador pinte un fotograma (en 0 suele ser negro).
    setSrc(posterUrl || archivo.includes("#") ? archivo : `${archivo}#t=0.1`);
    setAutoPermitido(!reducido && !ahorro);
  }, [videoUrl, videoMobileUrl, posterUrl]);

  // 2. Cuándo montar el <video>: el hero, apenas se sabe que puede arrancar; una
  //    sección, cuando se acerca a la pantalla. Si no puede arrancar solo y hay
  //    poster, nunca: se monta recién cuando piden reproducir. Sin poster se monta
  //    igual (sin arrancar), para mostrar el primer fotograma.
  useEffect(() => {
    if (autoPermitido === null || montado) return;
    if (!autoPermitido && posterUrl) return;
    if (modo === "hero") {
      setMontado(true);
      return;
    }
    const el = contenedorRef.current;
    if (!el) return;
    const cerca = new IntersectionObserver(
      ([entrada]) => {
        if (entrada.isIntersecting) {
          setMontado(true);
          cerca.disconnect();
        }
      },
      { rootMargin: "200px 0px" }
    );
    cerca.observe(el);
    return () => cerca.disconnect();
  }, [autoPermitido, modo, montado, posterUrl]);

  const intentarReproducir = useCallback(() => {
    const v = videoRef.current;
    if (!v) return;
    // React no siempre refleja el atributo muted: sin esto el navegador bloquea el autoplay.
    v.muted = silenciado;
    v.play().catch(() => {
      // Autoplay bloqueado (modo ahorro de batería, política del navegador): queda el
      // poster con el botón de play, sin error a la vista.
      setReproduciendo(false);
    });
  }, [silenciado]);

  // 3. Reproducir solo mientras está en pantalla y la pestaña visible.
  useEffect(() => {
    const el = contenedorRef.current;
    // Con fallo el <video> no está; al reintentar (fallo vuelve a false) se observa de nuevo.
    if (!montado || fallo || !el) return;

    const decidir = () => {
      const v = videoRef.current;
      if (!v) return;
      const quiere = (autoPermitido || pidioReproducir.current) && !pausadoPorUsuario.current;
      if (visibleRef.current && !document.hidden && quiere) intentarReproducir();
      else if (!v.paused) v.pause();
    };

    const enPantalla = new IntersectionObserver(
      ([entrada]) => {
        visibleRef.current = entrada.isIntersecting;
        decidir();
      },
      { threshold: 0.35 }
    );
    enPantalla.observe(el);
    document.addEventListener("visibilitychange", decidir);
    return () => {
      enPantalla.disconnect();
      document.removeEventListener("visibilitychange", decidir);
    };
  }, [montado, fallo, autoPermitido, intentarReproducir]);

  useEffect(() => {
    const alCambiar = () => setPantallaCompleta(document.fullscreenElement === contenedorRef.current);
    document.addEventListener("fullscreenchange", alCambiar);
    return () => document.removeEventListener("fullscreenchange", alCambiar);
  }, []);

  useEffect(() => () => {
    if (ocultarTactil.current) window.clearTimeout(ocultarTactil.current);
  }, []);

  const reproducirDesdePoster = () => {
    pidioReproducir.current = true;
    pausadoPorUsuario.current = false;
    setIniciado(true);
    setFallo(false);
    if (montado && videoRef.current) intentarReproducir();
    else setMontado(true); // el efecto 3 lo reproduce al estar visible
  };

  const alternarReproduccion = () => {
    const v = videoRef.current;
    if (!v) return;
    if (v.paused) {
      pausadoPorUsuario.current = false;
      pidioReproducir.current = true;
      intentarReproducir();
    } else {
      pausadoPorUsuario.current = true;
      v.pause();
    }
  };

  const alternarSonido = () => {
    const v = videoRef.current;
    if (!v) return;
    v.muted = !v.muted;
    setSilenciado(v.muted);
  };

  const alternarPantallaCompleta = () => {
    const el = contenedorRef.current;
    const v = videoRef.current as (HTMLVideoElement & { webkitEnterFullscreen?: () => void }) | null;
    if (!el) return;
    if (document.fullscreenElement) {
      void document.exitFullscreen();
    } else if (el.requestFullscreen) {
      void el.requestFullscreen();
    } else {
      // Safari en iPhone: solo el propio video puede ir a pantalla completa.
      v?.webkitEnterFullscreen?.();
    }
  };

  // En pantallas táctiles no hay hover: un toque muestra los controles un rato.
  const alTocar = (e: React.PointerEvent) => {
    if (e.pointerType !== "touch") return;
    setControlesTactiles(true);
    if (ocultarTactil.current) window.clearTimeout(ocultarTactil.current);
    ocultarTactil.current = window.setTimeout(() => setControlesTactiles(false), 3000);
  };

  const decorativo = !controles;
  // Botón de play grande, sobre el poster o el fondo oscuro: no puede arrancar solo
  // (movimiento reducido, ahorro de datos) y todavía no lo pidieron, o el video falló.
  const botonGrande = controles && autoPermitido !== null && (fallo || (!autoPermitido && !iniciado));

  return (
    <div
      ref={contenedorRef}
      onPointerDown={alTocar}
      className={cn(
        "group relative w-full overflow-hidden rounded-2xl bg-black ring-1 ring-white/10",
        proporcion,
        // Sin poster: fondo oscuro con algo de profundidad mientras llega el fotograma.
        !posterUrl && "bg-gradient-to-br from-neutral-800 via-neutral-950 to-black",
        pantallaCompleta && "rounded-none",
        className
      )}
    >
      {/* El poster siempre debajo: es lo primero que se ve y lo que queda si el video
          no se reproduce. Decorativo: el nombre lo lleva el video o el botón. */}
      {posterUrl && (
        <Image
          src={posterUrl}
          alt=""
          aria-hidden
          fill
          sizes={sizes}
          priority={prioridad}
          className={cn("object-cover", pantallaCompleta && "object-contain")}
        />
      )}

      {montado && src && !fallo && (
        <video
          ref={videoRef}
          src={src}
          poster={posterUrl ?? undefined}
          autoPlay={modo === "hero" && Boolean(autoPermitido)}
          muted
          loop
          playsInline
          preload="metadata"
          aria-label={decorativo ? undefined : t("video_label", { title: etiqueta })}
          aria-hidden={decorativo || undefined}
          tabIndex={-1}
          className={cn(
            "absolute inset-0 h-full w-full",
            pantallaCompleta ? "object-contain" : "object-cover"
          )}
          onPlay={(e) => {
            setReproduciendo(true);
            setCargando(e.currentTarget.readyState < 3);
          }}
          onPause={() => {
            setReproduciendo(false);
            setCargando(false);
          }}
          onWaiting={() => setCargando(true)}
          onPlaying={() => setCargando(false)}
          onTimeUpdate={(e) => setTiempo(e.currentTarget.currentTime)}
          // Un stream sin duración conocida (Infinity) deja la barra desactivada.
          onLoadedMetadata={(e) => {
            const v = e.currentTarget;
            // Un códec que el navegador no decodifica (p. ej. un .mov en HEVC) a veces
            // carga sin error pero sin imagen: se trata como fallo.
            if (v.videoWidth === 0) {
              setFallo(true);
              return;
            }
            setDuracion(Number.isFinite(v.duration) ? v.duration : 0);
          }}
          onDurationChange={(e) => setDuracion(Number.isFinite(e.currentTarget.duration) ? e.currentTarget.duration : 0)}
          onVolumeChange={(e) => setSilenciado(e.currentTarget.muted)}
          onError={() => {
            setFallo(true);
            setReproduciendo(false);
            setCargando(false);
          }}
        />
      )}

      {/* Cargando: sin esto, un video que tarda en llegar (archivo grande, conexión lenta)
          se ve como un recuadro vacío que no responde. */}
      {cargando && !fallo && !botonGrande && controles && (
        <div role="status" className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span className="h-10 w-10 animate-spin rounded-full border-2 border-white/25 border-t-white motion-reduce:animate-none" aria-hidden />
          <span className="sr-only">{t("loading")}</span>
        </div>
      )}

      {/* Poster (o fondo oscuro) con botón de reproducir: movimiento reducido, ahorro
          de datos o error. */}
      {botonGrande && (
        <button
          type="button"
          onClick={reproducirDesdePoster}
          aria-label={t("play_video", { title: etiqueta })}
          className="absolute inset-0 flex items-center justify-center bg-black/25 transition hover:bg-black/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white"
        >
          <span className="flex h-16 w-16 items-center justify-center rounded-full bg-black/60 text-white ring-1 ring-white/25 backdrop-blur-sm">
            <Play size={28} className="translate-x-0.5" aria-hidden />
          </span>
        </button>
      )}

      {/* Controles propios, en una barra al pie del video sobre un degradado oscuro.
          Con mouse: visibles al pasar por encima, al enfocar con teclado y siempre en
          pausa. En pantallas táctiles (sin hover) quedan siempre visibles: si estuvieran
          ocultos, el primer toque accionaría un botón invisible. Cada control mide 44px. */}
      {montado && !fallo && controles && !botonGrande && (
        <div
          className={cn(
            "absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/50 to-transparent px-2 pb-1 pt-10 transition-opacity duration-200",
            "opacity-0 group-hover:opacity-100 focus-within:opacity-100 motion-reduce:transition-none [@media(hover:none)]:opacity-100",
            (!reproduciendo || controlesTactiles) && "opacity-100"
          )}
        >
          <input
            type="range"
            min={0}
            max={duracion || 0}
            step={0.1}
            value={Math.min(tiempo, duracion || 0)}
            onChange={(e) => {
              const v = videoRef.current;
              if (v) v.currentTime = Number(e.target.value);
            }}
            disabled={!duracion}
            aria-label={t("progress")}
            aria-valuetext={t("time_of", { current: formatoTiempo(tiempo), total: formatoTiempo(duracion) })}
            // 44px de alto para el dedo; la pista se sigue viendo fina (va centrada).
            className="block h-11 w-full cursor-pointer px-1 accent-brand-red disabled:cursor-default"
          />
          <div className="flex items-center gap-0.5 text-white">
            <BotonControl onClick={alternarReproduccion} etiqueta={reproduciendo ? t("pause") : t("play")}>
              {reproduciendo ? <Pause size={18} aria-hidden /> : <Play size={18} aria-hidden />}
            </BotonControl>
            {conSonido && (
              <BotonControl onClick={alternarSonido} etiqueta={silenciado ? t("unmute") : t("mute")}>
                {silenciado ? <VolumeX size={18} aria-hidden /> : <Volume2 size={18} aria-hidden />}
              </BotonControl>
            )}
            <span className="ml-1 text-xs font-medium tabular-nums text-white/90" aria-hidden>
              {formatoTiempo(tiempo)} / {formatoTiempo(duracion)}
            </span>
            <BotonControl
              onClick={alternarPantallaCompleta}
              etiqueta={pantallaCompleta ? t("exit_fullscreen") : t("fullscreen")}
              className="ml-auto"
            >
              {pantallaCompleta ? <Minimize size={18} aria-hidden /> : <Maximize size={18} aria-hidden />}
            </BotonControl>
          </div>
        </div>
      )}
    </div>
  );
}

function BotonControl({
  onClick,
  etiqueta,
  className,
  children,
}: {
  onClick: () => void;
  etiqueta: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={etiqueta}
      title={etiqueta}
      className={cn(
        "grid h-11 w-11 shrink-0 place-items-center rounded-full transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white",
        className
      )}
    >
      {children}
    </button>
  );
}

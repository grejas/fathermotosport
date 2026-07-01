"use client";

import { Suspense, useState } from "react";
import { Canvas } from "@react-three/fiber";
import { Maximize2, RotateCw } from "lucide-react";
import { Loader } from "./Loader";
import { Lights } from "./Lights";
import { Environment } from "./Environment";
import { Controls } from "./Controls";
import { HelmetModel } from "./HelmetModel";
import { visorColors, type VisorColor } from "./Materials";

interface ProductViewer3DProps {
  url: string;
  showVisorColors?: boolean;
}

export function ProductViewer3D({ url, showVisorColors = true }: ProductViewer3DProps) {
  const [autoRotate, setAutoRotate] = useState(true);
  const [visor, setVisor] = useState<VisorColor | null>(null);

  const toggleFullscreen = (e: React.MouseEvent<HTMLButtonElement>) => {
    const container = e.currentTarget.closest("[data-viewer]");
    if (container) {
      if (document.fullscreenElement) document.exitFullscreen();
      else (container as HTMLElement).requestFullscreen?.();
    }
  };

  return (
    <div
      data-viewer
      className="relative aspect-square w-full overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-brand-dark to-brand-carbon"
    >
      <Canvas camera={{ position: [0, 0, 4], fov: 45 }} gl={{ alpha: true, antialias: true }} shadows>
        <Suspense fallback={<Loader />}>
          <Lights />
          <HelmetModel url={url} visorColor={visor} />
          <Environment />
        </Suspense>
        <Controls autoRotate={autoRotate} />
      </Canvas>

      {/* Badge 360 */}
      <div className="absolute left-3 top-3 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1 text-xs font-bold text-brand-white backdrop-blur">
        <span className="h-2 w-2 animate-pulse rounded-full bg-brand-red" /> 360° 3D
      </div>

      {/* Controles */}
      <div className="absolute right-3 top-3 flex gap-2">
        <button
          onClick={() => setAutoRotate((v) => !v)}
          className={`rounded-lg p-2 backdrop-blur transition ${
            autoRotate ? "bg-brand-red text-white" : "bg-black/60 text-brand-white"
          }`}
          aria-label="Auto-rotación"
        >
          <RotateCw size={16} />
        </button>
        <button
          onClick={toggleFullscreen}
          className="rounded-lg bg-black/60 p-2 text-brand-white backdrop-blur transition hover:bg-black/80"
          aria-label="Pantalla completa"
        >
          <Maximize2 size={16} />
        </button>
      </div>

      {/* Selector de color de visor */}
      {showVisorColors && (
        <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2 rounded-full bg-black/60 px-3 py-2 backdrop-blur">
          {visorColors.map((c) => (
            <button
              key={c.id}
              onClick={() => setVisor(c)}
              title={c.label}
              className={`h-6 w-6 rounded-full border-2 transition ${
                visor?.id === c.id ? "border-white scale-110" : "border-white/30"
              }`}
              style={{ backgroundColor: c.hex }}
              aria-label={`Visor ${c.label}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}

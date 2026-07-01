"use client";

import { Html, useProgress } from "@react-three/drei";

/** Fallback dentro del Canvas mientras carga el GLB. */
export function Loader() {
  const { progress } = useProgress();
  return (
    <Html center>
      <div className="flex flex-col items-center gap-3">
        <div className="h-10 w-10 animate-spin rounded-full border-2 border-white/20 border-t-brand-red" />
        <p className="text-xs font-medium text-brand-white">{Math.round(progress)}%</p>
      </div>
    </Html>
  );
}

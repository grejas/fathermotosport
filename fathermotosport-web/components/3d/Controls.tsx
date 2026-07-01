"use client";

import { OrbitControls } from "@react-three/drei";

interface ControlsProps {
  autoRotate?: boolean;
}

export function Controls({ autoRotate = true }: ControlsProps) {
  return (
    <OrbitControls
      enableZoom
      enablePan={false}
      autoRotate={autoRotate}
      autoRotateSpeed={1.2}
      minPolarAngle={Math.PI / 4}
      maxPolarAngle={Math.PI / 1.6}
      minDistance={2}
      maxDistance={6}
    />
  );
}

"use client";

import { useRef } from "react";
import { useFrame } from "@react-three/fiber";
import type * as THREE from "three";

/** Animación de flotación suave para el modelo del casco. */
export function useHelmetFloat() {
  const ref = useRef<THREE.Group>(null);

  useFrame((state) => {
    if (!ref.current) return;
    const t = state.clock.getElapsedTime();
    ref.current.position.y = Math.sin(t * 0.8) * 0.06;
  });

  return ref;
}

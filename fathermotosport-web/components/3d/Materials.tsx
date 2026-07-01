"use client";

import * as THREE from "three";

export interface VisorColor {
  id: string;
  label: string;
  hex: string;
  metalness: number;
  roughness: number;
}

/** Paleta de colores de visor para cascos. */
export const visorColors: VisorColor[] = [
  { id: "iridium", label: "Iridio", hex: "#7d7f86", metalness: 1, roughness: 0.1 },
  { id: "blue", label: "Azul espejo", hex: "#1e63d8", metalness: 0.9, roughness: 0.15 },
  { id: "smoke", label: "Humo", hex: "#2b2b2b", metalness: 0.3, roughness: 0.4 },
  { id: "gold", label: "Gold", hex: "#C9A84C", metalness: 1, roughness: 0.12 },
  { id: "red", label: "Rojo espejo", hex: "#E8001D", metalness: 0.9, roughness: 0.15 },
];

/** Aplica un color de visor a las mallas cuyo nombre contiene "visor". */
export function applyVisorColor(root: THREE.Object3D | null, color: VisorColor): void {
  if (!root) return;
  root.traverse((child) => {
    if (
      child instanceof THREE.Mesh &&
      /visor|shield|glass/i.test(child.name) &&
      child.material instanceof THREE.MeshStandardMaterial
    ) {
      child.material.color = new THREE.Color(color.hex);
      child.material.metalness = color.metalness;
      child.material.roughness = color.roughness;
      child.material.needsUpdate = true;
    }
  });
}

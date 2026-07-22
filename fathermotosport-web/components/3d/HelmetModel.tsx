"use client";

import { useEffect, useState } from "react";
import { Center, useGLTF } from "@react-three/drei";
import * as THREE from "three";
import { useHelmetFloat } from "./Animations";
import { applyVisorColor, type VisorColor } from "./Materials";

interface HelmetModelProps {
  url: string;
  scale?: number;
  visorColor?: VisorColor | null;
}

/** Nombre de mallas que cuentan como visor real dentro del GLB. */
const VISOR_MESH_REGEX = /visor|shield|glass/i;

export function HelmetModel({ url, scale = 1, visorColor }: HelmetModelProps) {
  const { scene } = useGLTF(url, true);
  const ref = useHelmetFloat();
  const [hasVisorGeometry, setHasVisorGeometry] = useState(true);

  useEffect(() => {
    let found = false;
    scene.traverse((child) => {
      if (child instanceof THREE.Mesh && VISOR_MESH_REGEX.test(child.name)) found = true;
    });
    setHasVisorGeometry(found);
  }, [scene]);

  useEffect(() => {
    if (visorColor) applyVisorColor(scene, visorColor);
  }, [scene, visorColor]);

  return (
    <group ref={ref}>
      <Center>
        <primitive object={scene} scale={scale} />
        {!hasVisorGeometry && (
          // Visera sintética: el GLB no trae una malla de visor propia, así
          // que se simula una con una calota esférica curvada al frente del casco.
          <mesh position={[0, 0.05, 0.5]} rotation={[Math.PI / 2, 0, 0]} scale={[1.1, 0.85, 0.9]}>
            <sphereGeometry args={[0.65, 32, 16, 0, Math.PI * 2, 0, Math.PI * 0.45]} />
            <meshPhysicalMaterial
              color={visorColor ? visorColor.hex : "#0a0a0a"}
              transparent
              opacity={visorColor ? 0.3 : 0.25}
              roughness={0}
              metalness={0.1}
              side={THREE.DoubleSide}
            />
          </mesh>
        )}
      </Center>
    </group>
  );
}

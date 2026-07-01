"use client";

import { useEffect } from "react";
import { Center, useGLTF } from "@react-three/drei";
import { useHelmetFloat } from "./Animations";
import { applyVisorColor, type VisorColor } from "./Materials";

interface HelmetModelProps {
  url: string;
  scale?: number;
  visorColor?: VisorColor | null;
}

export function HelmetModel({ url, scale = 1, visorColor }: HelmetModelProps) {
  const { scene } = useGLTF(url);
  const ref = useHelmetFloat();

  useEffect(() => {
    if (visorColor) applyVisorColor(scene, visorColor);
  }, [scene, visorColor]);

  return (
    <group ref={ref}>
      <Center>
        <primitive object={scene} scale={scale} />
      </Center>
    </group>
  );
}

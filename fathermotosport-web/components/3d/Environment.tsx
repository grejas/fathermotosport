"use client";

import { ContactShadows, Environment as DreiEnvironment } from "@react-three/drei";

/** Entorno HDRI + sombra de contacto bajo el modelo. */
export function Environment() {
  return (
    <>
      <DreiEnvironment preset="city" />
      <ContactShadows position={[0, -1, 0]} opacity={0.5} scale={8} blur={2.5} far={3} />
    </>
  );
}

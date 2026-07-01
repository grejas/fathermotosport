"use client";

/** Iluminación de estudio con un acento cálido (gold). */
export function Lights() {
  return (
    <>
      <ambientLight intensity={0.4} />
      <directionalLight position={[5, 5, 5]} intensity={1.1} castShadow />
      <directionalLight position={[-3, 2, -3]} intensity={0.6} color="#C9A84C" />
    </>
  );
}

"use client";

import { Component, type ReactNode } from "react";
import { Html } from "@react-three/drei";
import { AlertTriangle } from "lucide-react";

interface ModelErrorBoundaryProps {
  children: ReactNode;
}

interface ModelErrorBoundaryState {
  hasError: boolean;
}

/**
 * Captura errores de carga del GLB (red/CORS/parseo/Draco) para no tirar
 * el árbol de react-three-fiber completo (lo que provoca "Context Lost").
 * Se debe usar DENTRO de <Canvas>, envolviendo el <Suspense> del modelo;
 * por eso el fallback usa <Html> de drei en vez de un <div> plano.
 */
export class ModelErrorBoundary extends Component<ModelErrorBoundaryProps, ModelErrorBoundaryState> {
  state: ModelErrorBoundaryState = { hasError: false };

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error: unknown) {
    console.error("[ModelErrorBoundary] No se pudo cargar el modelo 3D:", error);
  }

  render() {
    if (this.state.hasError) {
      return (
        <Html center>
          <div className="flex w-56 flex-col items-center gap-2 text-center text-brand-muted">
            <AlertTriangle size={28} className="text-brand-red" />
            <p className="text-sm font-medium text-brand-white">No se pudo cargar el modelo 3D</p>
            <p className="text-xs">Probá recargar la página</p>
          </div>
        </Html>
      );
    }

    return this.props.children;
  }
}

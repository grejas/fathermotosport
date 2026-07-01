"use client";

import { useEffect, useRef } from "react";

/** Fondo animado: grid de puntos que ondula + partículas rojas orbitando. */
export function HeroParticles() {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let raf = 0;
    let w = 0;
    let h = 0;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    const resize = () => {
      w = canvas.offsetWidth;
      h = canvas.offsetHeight;
      canvas.width = w * dpr;
      canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener("resize", resize);

    const gap = 38;
    const orbiters = Array.from({ length: 14 }).map((_, i) => ({
      angle: (i / 14) * Math.PI * 2,
      radius: 80 + Math.random() * 180,
      speed: 0.002 + Math.random() * 0.004,
      size: 1.5 + Math.random() * 2,
    }));

    const draw = (t: number) => {
      ctx.clearRect(0, 0, w, h);

      // Grid de puntos con ondulación
      for (let x = 0; x < w; x += gap) {
        for (let y = 0; y < h; y += gap) {
          const wave = Math.sin(x * 0.01 + t * 0.001) + Math.cos(y * 0.01 + t * 0.0012);
          const alpha = 0.05 + (wave + 2) * 0.04;
          ctx.fillStyle = `rgba(245,245,245,${alpha})`;
          ctx.beginPath();
          ctx.arc(x, y + wave * 3, 1.1, 0, Math.PI * 2);
          ctx.fill();
        }
      }

      // Partículas rojas orbitando el centro
      const cx = w * 0.7;
      const cy = h * 0.45;
      orbiters.forEach((o) => {
        o.angle += o.speed;
        const px = cx + Math.cos(o.angle) * o.radius;
        const py = cy + Math.sin(o.angle) * o.radius * 0.6;
        const grad = ctx.createRadialGradient(px, py, 0, px, py, o.size * 4);
        grad.addColorStop(0, "rgba(232,0,29,0.9)");
        grad.addColorStop(1, "rgba(232,0,29,0)");
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(px, py, o.size * 4, 0, Math.PI * 2);
        ctx.fill();
      });

      raf = requestAnimationFrame(draw);
    };
    raf = requestAnimationFrame(draw);

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
    };
  }, []);

  return <canvas ref={canvasRef} className="absolute inset-0 h-full w-full" aria-hidden />;
}

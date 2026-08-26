import type { Review, ReviewsSummary } from "@/lib/types";

/**
 * ⚠️ DATOS TEMPORALES DE PRUEBA — NO SON RESEÑAS REALES DE CLIENTES.
 *
 * Se agregaron para verificar visualmente la sección de Reseñas y
 * Valoraciones (ReviewsSection → RatingSummary/ReviewCard) mientras el
 * sistema real está en fase de pruebas y todavía no hay suficientes
 * reseñas aprobadas para evaluar el diseño en producción.
 *
 * No están conectadas a la base de datos, a la API ni al panel de admin —
 * viven únicamente acá, en el bundle del frontend.
 *
 * CÓMO ELIMINARLAS cuando el sistema real ya tenga reseñas de sobra:
 *   1. Borrar este archivo.
 *   2. En components/home/ReviewsSection.tsx, quitar el import de
 *      `demoReviews`/`demoReviewsSummary` y las líneas marcadas `// TEMPORAL`.
 */
export const demoReviews: Review[] = [
  // TEMPORAL: reseña demo #1
  {
    id: "demo-1",
    rating: 5,
    title: "Un casco de otro nivel",
    comment:
      "La calidad de los materiales y el acabado son impresionantes. Se siente firme en la cabeza y no genera puntos de presión ni en viajes largos.",
    is_approved: true,
    user: { id: "demo-user-1", name: "Carlos Mendoza", avatar: null },
    product: {
      id: "demo-product-1",
      name: "AGV Pista GP R Project 2.0 46",
      slug: "demo-agv-pista-gpr-project-2-0-46",
    },
    created_at: "2026-02-14T10:15:00.000Z",
  },
  // TEMPORAL: reseña demo #2
  {
    id: "demo-2",
    rating: 5,
    title: "Diseño espectacular",
    comment:
      "El diseño llama la atención por donde pase, pero además cumple: buena ventilación y el visor tiene un campo de visión excelente.",
    is_approved: true,
    user: { id: "demo-user-2", name: "Daniel Rojas", avatar: null },
    product: {
      id: "demo-product-2",
      name: "AGV Pista GPR Italia Forgiato",
      slug: "demo-agv-pista-gpr-italia-forgiato",
    },
    created_at: "2026-03-21T14:40:00.000Z",
  },
  // TEMPORAL: reseña demo #3
  {
    id: "demo-3",
    rating: 4,
    title: "Muy bueno, ajuste algo justo",
    comment:
      "Excelente terminación y se nota la calidad, aunque me quedó un poco ajustado al principio. Con un par de usos se fue acomodando bien.",
    is_approved: true,
    user: { id: "demo-user-3", name: "Miguel Herrera", avatar: null },
    product: {
      id: "demo-product-3",
      name: "Casco AGV Pista GP R Yamaha R1M",
      slug: "demo-casco-agv-pista-gpr-yamaha-r1m",
    },
    created_at: "2026-04-08T09:05:00.000Z",
  },
  // TEMPORAL: reseña demo #4
  {
    id: "demo-4",
    rating: 5,
    title: "Ventilación excelente",
    comment:
      "Lo uso para viajes largos y la ventilación se nota incluso en clima cálido. Además es bastante silencioso a alta velocidad.",
    is_approved: true,
    user: { id: "demo-user-4", name: "Andrés Vargas", avatar: null },
    product: {
      id: "demo-product-4",
      name: "Casco Shoei X-Fourteen BMW",
      slug: "demo-casco-shoei-x-fourteen-bmw",
    },
    created_at: "2026-05-05T17:30:00.000Z",
  },
  // TEMPORAL: reseña demo #5
  {
    id: "demo-5",
    rating: 5,
    title: "Agarre y protección de primera",
    comment:
      "Se sienten muy seguros en las manos, buen tacto con los controles y la protección en los nudillos es sólida sin restar movilidad.",
    is_approved: true,
    user: { id: "demo-user-5", name: "Luis Fernández", avatar: null },
    product: {
      id: "demo-product-5",
      name: "GUANTES ALPINESTARS GP PRO R4",
      slug: "demo-guantes-alpinestars-gp-pro-r4",
    },
    created_at: "2026-05-25T12:00:00.000Z",
  },
  // TEMPORAL: reseña demo #6
  {
    id: "demo-6",
    rating: 4,
    title: "Edición espectacular, entrega lenta",
    comment:
      "El acabado de esta edición es una belleza, se nota el detalle. La entrega tardó más de lo esperado pero el casco lo vale.",
    is_approved: true,
    user: { id: "demo-user-6", name: "Jorge Castillo", avatar: null },
    product: {
      id: "demo-product-6",
      name: "Casco AGV Pista GP R Joan Mir World Champion",
      slug: "demo-casco-agv-pista-gpr-joan-mir-world-champion",
    },
    created_at: "2026-06-17T20:10:00.000Z",
  },
  // TEMPORAL: reseña demo #7
  {
    id: "demo-7",
    rating: 5,
    title: "Vale cada peso",
    comment:
      "El carbono se ve espectacular y es notablemente más liviano que otros cascos que probé antes. Compra 100% recomendada para quien busca algo premium.",
    is_approved: true,
    user: { id: "demo-user-7", name: "Mateo Salazar", avatar: null },
    product: {
      id: "demo-product-7",
      name: "Casco AGV Pista GP R Carbon Iridium",
      slug: "demo-casco-agv-pista-gpr-carbon-iridium",
    },
    created_at: "2026-07-30T08:45:00.000Z",
  },
];

/**
 * ⚠️ TEMPORAL — resumen calculado a mano para las 7 reseñas de arriba, para
 * que RatingSummary se vea coherente con lo mostrado en la grilla mientras
 * dura la prueba visual. Eliminar junto con `demoReviews`.
 *
 * Ratings: 5,5,4,5,5,4,5 → total 7, promedio 33/7 = 4.7142... ≈ 4.7
 * Distribución: 5★ = 5/7 = 71%, 4★ = 2/7 = 29%
 */
export const demoReviewsSummary: ReviewsSummary = {
  average: 4.7,
  total: 7,
  distribution: [
    { rating: 5, count: 5, percent: 71 },
    { rating: 4, count: 2, percent: 29 },
    { rating: 3, count: 0, percent: 0 },
    { rating: 2, count: 0, percent: 0 },
    { rating: 1, count: 0, percent: 0 },
  ],
};

import type { Metadata } from "next";
import { LegalPage, type LegalSection } from "@/components/legal/LegalPage";
import { LEGAL } from "@/components/legal/entity";

export const metadata: Metadata = {
  title: "Política de Privacidad",
  description: "Cómo FATHERSON IMP LLC recoge, usa y protege tus datos personales en fathermotosport.com.",
};

const sections: LegalSection[] = [
  {
    heading: "Responsable",
    body: [
      `El responsable del tratamiento de tus datos es ${LEGAL.name} (${LEGAL.type}, EIN ${LEGAL.ein}), ${LEGAL.address}. Contacto: ${LEGAL.email}.`,
    ],
  },
  {
    heading: "Datos que recogemos",
    body: [
      {
        list: [
          "Nombre, correo electrónico y teléfono.",
          "Dirección de envío.",
          "Historial de pedidos.",
          "Datos técnicos: dirección IP, cookies y navegador.",
        ],
      },
    ],
  },
  {
    heading: "Uso de los datos",
    body: [
      {
        list: [
          "Procesar y enviar tus pedidos.",
          "Atención al cliente.",
          "Prevención del fraude.",
          "Comunicaciones de marketing, solo con tu consentimiento.",
        ],
      },
    ],
  },
  {
    heading: "Terceros",
    body: [
      "Compartimos los datos necesarios con procesadores de pago (Stripe), transportistas y proveedores de hosting y analítica, únicamente para prestar el servicio. No vendemos datos personales.",
    ],
  },
  {
    heading: "Cookies",
    body: [
      "Usamos cookies para el funcionamiento del sitio (carrito, sesión, idioma) y para medir el uso. Puedes bloquearlas desde la configuración de tu navegador, aunque algunas funciones podrían dejar de funcionar.",
    ],
  },
  {
    heading: "Conservación de datos",
    body: [
      "Conservamos tus datos mientras sean necesarios para cumplir los fines descritos y las obligaciones legales y contables aplicables.",
    ],
  },
  {
    heading: "Seguridad",
    body: [
      "Aplicamos medidas técnicas y organizativas razonables para proteger tus datos. Los datos de pago son gestionados por el procesador de pagos y no se almacenan en nuestros servidores.",
    ],
  },
  {
    heading: "Tus derechos",
    body: [
      `Puedes solicitar el acceso, la corrección o la eliminación de tus datos escribiendo a ${LEGAL.email}.`,
    ],
  },
];

export default function PrivacyPage() {
  return <LegalPage title="Política de Privacidad" sections={sections} updated={LEGAL.updated} />;
}

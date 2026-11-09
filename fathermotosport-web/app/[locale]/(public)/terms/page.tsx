import type { Metadata } from "next";
import { LegalPage, type LegalSection } from "@/components/legal/LegalPage";
import { LEGAL } from "@/components/legal/entity";

export const metadata: Metadata = {
  title: "Términos y Condiciones",
  description: "Términos y condiciones de compra y uso de fathermotosport.com, operado por FATHERSON IMP LLC.",
};

const sections: LegalSection[] = [
  {
    heading: "Quiénes somos",
    body: [
      `El sitio fathermotosport.com y la marca FatherMotoSport son operados por ${LEGAL.name} (${LEGAL.type}, EIN ${LEGAL.ein}), con domicilio en ${LEGAL.address}. Correo de contacto: ${LEGAL.email}.`,
      "Al navegar por el sitio o realizar una compra aceptas estos Términos y Condiciones.",
    ],
  },
  {
    heading: "Productos y precios",
    body: [
      "Todos los precios se muestran en dólares estadounidenses (USD).",
      "Nos reservamos el derecho de corregir errores de precio o de stock y de cancelar los pedidos afectados, con reembolso completo del importe pagado.",
    ],
  },
  {
    heading: "Pagos",
    body: [
      "Los pagos son procesados por proveedores externos (Stripe y otros). No almacenamos los datos completos de tu tarjeta.",
    ],
  },
  {
    heading: "Envíos y devoluciones",
    body: [
      {
        text: "Los plazos, costos y condiciones de envío y devolución están descritos en nuestra",
        href: "/returns-policy",
        linkText: "Política de Envío y Devoluciones.",
      },
    ],
  },
  {
    heading: "Garantía y responsabilidad",
    body: [
      "Los productos que vendemos son de terceros y cuentan con la garantía del fabricante.",
      "Nuestra responsabilidad frente al cliente se limita al valor pagado por el pedido.",
    ],
  },
  {
    heading: "Ley aplicable",
    body: ["Estos términos se rigen por las leyes del Estado de Wyoming, EE.UU."],
  },
  {
    heading: "Contacto",
    body: [`Escríbenos a ${LEGAL.email} o por WhatsApp al ${LEGAL.whatsapp}.`],
  },
];

export default function TermsPage() {
  return <LegalPage title="Términos y Condiciones" sections={sections} updated={LEGAL.updated} />;
}

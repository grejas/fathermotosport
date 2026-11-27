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
    heading: "Impuestos y aranceles de importación",
    body: [
      "Los pedidos se envían desde el exterior. Los precios no incluyen los impuestos ni los aranceles de importación del país de destino (por ejemplo, el IVA en la Unión Europea o el Imposto de Importação y el ICMS en Brasil).",
      "Si la aduana o el transportista los cobra al recibir el paquete, corren por cuenta de quien recibe el pedido. Te lo avisamos también en el pago, antes de confirmar la compra.",
    ],
  },
  {
    heading: "Derecho de desistimiento",
    body: [
      "Tienes 14 días naturales desde que recibes el pedido para desistir de la compra sin indicar el motivo. Te devolvemos todo lo pagado, incluido el envío original, dentro de los 14 días siguientes a recibir tu decisión.",
      {
        text: "Puedes ejercerlo desde el formulario",
        href: "/withdrawal",
        linkText: "Desistir de una compra.",
      },
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
      "Los productos que vendemos son de terceros y cuentan con la garantía del fabricante, además de la garantía legal que te reconoce la ley de tu país (por ejemplo, 90 días en Brasil y 2 años en la Unión Europea).",
      "Nuestra responsabilidad frente al cliente se limita al valor pagado por el pedido, salvo en caso de dolo o culpa grave, daños a la vida o a la salud, o cuando la ley aplicable no permita limitarla.",
    ],
  },
  {
    heading: "Ley aplicable",
    body: [
      "Estos términos se rigen por las leyes del Estado de Wyoming, EE.UU.",
      "Si compras como consumidor, esta elección no te priva de la protección que te dan las normas obligatorias del país donde vives (por ejemplo, el Código de Defensa del Consumidor de Brasil o la normativa de consumo de la Unión Europea).",
    ],
  },
  {
    heading: "Contacto",
    body: [`Escríbenos a ${LEGAL.email} o por WhatsApp al ${LEGAL.whatsapp}.`],
  },
];

export default function TermsPage() {
  return <LegalPage title="Términos y Condiciones" sections={sections} updated={LEGAL.updated} />;
}

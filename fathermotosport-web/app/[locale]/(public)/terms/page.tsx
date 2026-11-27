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
      "Puedes desistir de la compra sin indicar el motivo dentro del plazo que te reconoce la ley de tu país, contado desde la entrega (por ejemplo, 14 días en la Unión Europea, 10 en Argentina y Chile y 7 en Brasil). Te devolvemos todo lo pagado, incluido el envío original, dentro de los 14 días siguientes a recibir tu decisión. El envío de vuelta corre por cuenta del cliente, salvo que la ley aplicable lo ponga a cargo del vendedor.",
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
    heading: "Propiedad intelectual",
    body: [
      "Los textos, fotos, videos, logos y el diseño del sitio pertenecen a FatherMotoSport o a sus titulares y no pueden copiarse ni usarse con fines comerciales sin autorización. Las marcas de los fabricantes (cascos, ropa y accesorios) pertenecen a sus respectivos dueños y se mencionan solo para identificar los productos.",
    ],
  },
  {
    heading: "Edad mínima",
    body: [
      "Para comprar en el sitio debes ser mayor de edad según la ley de tu país (18 años en la mayoría). Si eres menor, la compra debe hacerla tu padre, madre o tutor.",
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
    heading: "Reclamos y resolución de disputas",
    body: [
      `Si tienes un problema con tu pedido, escríbenos primero a ${LEGAL.email}: respondemos en un máximo de 5 días hábiles y buscamos una solución en 30 días.`,
      "Si no llegamos a un acuerdo, puedes acudir a los tribunales o a la autoridad de consumo del país donde vives. Nada en estos términos te obliga a renunciar a ese derecho ni a un arbitraje.",
    ],
  },
  {
    heading: "Otras disposiciones",
    body: [
      "Si alguna cláusula de estos términos resulta inválida, el resto sigue vigente.",
      "Estos términos están redactados en español. Si te mostramos una traducción y hay diferencias, prevalece la versión en español, salvo que la ley de tu país disponga otra cosa.",
      "Podemos actualizar estos términos; cada pedido se rige por la versión vigente el día en que lo hiciste, que queda registrada con tu aceptación en el checkout.",
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

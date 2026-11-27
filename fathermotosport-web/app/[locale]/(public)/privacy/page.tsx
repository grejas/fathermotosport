import type { Metadata } from "next";
import { LegalPage, type LegalSection } from "@/components/legal/LegalPage";
import { LEGAL } from "@/components/legal/entity";

export const metadata: Metadata = {
  title: "Política de Privacidad y Cookies",
  description:
    "Cómo FATHERSON IMP LLC trata tus datos personales en fathermotosport.com: finalidades, bases legales, proveedores, transferencias, cookies y tus derechos (RGPD, LGPD y CCPA).",
};

/**
 * Política de privacidad y cookies (auditoría legal, 27-nov-2026). Cubre RGPD (UE/EEE y
 * Reino Unido), LGPD (Brasil) y leyes estatales de EE.UU. (CCPA/CPRA y similares).
 * Si se agrega un proveedor o una cookie, actualizar "Con quién compartimos tus datos" o
 * "Cookies" y la fecha de LEGAL.updated.
 */
const sections: LegalSection[] = [
  {
    heading: "Quién es el responsable",
    body: [
      `El responsable del tratamiento de tus datos es ${LEGAL.name} (${LEGAL.type}, EIN ${LEGAL.ein}), con domicilio en ${LEGAL.address}, que opera fathermotosport.com y la marca FatherMotoSport.`,
      `Para cualquier consulta sobre tus datos o para ejercer tus derechos escríbenos a ${LEGAL.email}, indicando en el asunto "Privacidad". Esta misma dirección es el canal de contacto del encargado de protección de datos (encarregado, en Brasil).`,
    ],
  },
  {
    heading: "Qué datos tratamos",
    body: [
      {
        list: [
          "Identificación y contacto: nombre, correo electrónico, teléfono o WhatsApp.",
          "Envío: dirección, ciudad, país y referencias de entrega.",
          "Pedidos: productos, tallas, importes, método de pago elegido, estado del pedido y del envío, y la fecha en que aceptaste los Términos.",
          "Pago: el resultado del pago y los últimos dígitos de la tarjeta que nos informa el procesador. Nunca vemos ni guardamos el número completo de tu tarjeta.",
          "Cuenta: correo, contraseña cifrada y, si entras con Google, tu nombre y correo de Google.",
          "Comunicaciones: mensajes que nos envías por correo, WhatsApp, Instagram o TikTok, reseñas y solicitudes de desistimiento.",
          "Datos técnicos y de navegación: dirección IP, navegador, dispositivo, páginas visitadas y cookies, según lo que autorices en el banner de cookies.",
        ],
      },
      "No tratamos datos sensibles (salud, origen, religión, etc.) y la tienda no está dirigida a menores de 18 años.",
    ],
  },
  {
    heading: "Para qué los usamos y con qué base legal",
    body: [
      {
        list: [
          "Gestionar tu compra, el pago, el envío, las devoluciones y la garantía. Base: ejecución del contrato.",
          "Atenderte por correo, WhatsApp o redes. Base: ejecución del contrato o tu solicitud.",
          "Cumplir obligaciones contables, fiscales y aduaneras. Base: obligación legal.",
          "Prevenir fraudes y proteger la tienda (por ejemplo, reCAPTCHA y controles de Stripe). Base: interés legítimo.",
          "Enviarte ofertas y cupones por correo o WhatsApp. Base: tu consentimiento, que puedes retirar en cualquier momento con el enlace de baja o escribiéndonos.",
          "Medir visitas (Google Analytics) y medir y personalizar anuncios (píxeles de Meta y TikTok). Base: tu consentimiento en el banner de cookies.",
        ],
      },
      "No tomamos decisiones automatizadas con efectos legales sobre ti ni elaboramos perfiles de ese tipo.",
    ],
  },
  {
    heading: "Con quién compartimos tus datos",
    body: [
      "Solo con proveedores que necesitamos para operar la tienda, que actúan por cuenta nuestra (encargados del tratamiento):",
      {
        list: [
          "Stripe (pagos con tarjeta y billeteras digitales).",
          "Hostinger (servidor y correo electrónico) y Vercel (alojamiento de la web).",
          "Resend (envío de correos automáticos, como la confirmación del pedido).",
          "Proveedores y fabricantes que preparan tu pedido, y empresas de transporte y logística (nombre, dirección y teléfono de entrega).",
          "Mercury (cuenta bancaria para transferencias en EE.UU.) y Binance (pagos con cripto), solo si eliges esos medios.",
          "Google (inicio de sesión con Google, reCAPTCHA y, si lo autorizas, Google Analytics).",
          "Meta (WhatsApp, Instagram y Facebook) y TikTok, para atenderte por esos canales y, si lo autorizas, para medir anuncios.",
          "Autoridades aduaneras o públicas, cuando la ley lo exige.",
        ],
      },
      "No vendemos tus datos personales. Con tu consentimiento, los píxeles de Meta y TikTok comparten datos de navegación con esas plataformas con fines publicitarios, lo que algunas leyes de EE.UU. llaman \"compartir\" (sharing). Puedes desactivarlo en cualquier momento desde \"Cookies y tus opciones de privacidad\" en el pie de página, y respetamos la señal Global Privacy Control de tu navegador.",
    ],
  },
  {
    heading: "Transferencias internacionales",
    body: [
      "Somos una empresa de EE.UU. y vendemos a América y Europa, así que tus datos se tratan en EE.UU. y, para preparar y enviar tu pedido, también en el país del proveedor (por ejemplo, China) y en los países de tránsito.",
      "Cuando tus datos salen de la Unión Europea, el Reino Unido o Brasil, lo hacemos porque es necesario para cumplir el contrato de compra que pediste, o con garantías como las cláusulas contractuales tipo de la Comisión Europea, el Data Privacy Framework UE-EE.UU. cuando el proveedor está adherido, o las cláusulas aprobadas por la ANPD. Puedes pedirnos una copia de esas garantías.",
    ],
  },
  {
    heading: "Cuánto tiempo los conservamos",
    body: [
      {
        list: [
          "Pedidos, facturas y pagos: 7 años desde la compra, por obligaciones contables y fiscales.",
          "Solicitudes de desistimiento, reclamos y garantías: hasta 5 años, para atender posibles reclamaciones.",
          "Cuenta de cliente: mientras la mantengas activa; al cerrarla borramos o anonimizamos los datos que no debamos conservar por ley.",
          "Marketing: hasta que retires tu consentimiento o te des de baja.",
          "Cookies: según la duración indicada en la sección Cookies.",
        ],
      },
    ],
  },
  {
    heading: "Tus derechos",
    body: [
      `Puedes ejercerlos gratis escribiendo a ${LEGAL.email}. Podemos pedirte que confirmes tu identidad (por ejemplo, desde el correo de tu compra) y te respondemos en un máximo de 15 días (el plazo más corto entre las leyes que aplican).`,
      {
        list: [
          "Unión Europea, EEE y Reino Unido (RGPD): acceso, rectificación, supresión, limitación, oposición, portabilidad y retirar tu consentimiento en cualquier momento. También puedes reclamar ante la autoridad de protección de datos de tu país.",
          "Brasil (LGPD, art. 18): confirmación de que tratamos tus datos, acceso, corrección, anonimización, bloqueo o eliminación, portabilidad, información sobre con quién los compartimos, revocar tu consentimiento y reclamar ante la ANPD.",
          "Estados Unidos (CCPA/CPRA y leyes estatales similares): saber qué datos tenemos y con quién los compartimos, pedir que los corrijamos o borremos, y excluirte de la \"venta o compartición\" con fines publicitarios desde \"Cookies y tus opciones de privacidad\". No te discriminaremos por ejercer tus derechos. Un agente autorizado puede pedirlo en tu nombre con tu autorización por escrito.",
          "Otros países: los derechos que te reconozca tu ley de protección de datos.",
        ],
      },
    ],
  },
  {
    heading: "Cookies",
    body: [
      "Usamos cookies y almacenamiento del navegador. Las necesarias funcionan siempre; las de analítica y marketing solo si las aceptas en el banner. Puedes cambiar tu elección cuando quieras desde \"Cookies y tus opciones de privacidad\" en el pie de página.",
      {
        list: [
          "Necesarias: sesión, carrito, idioma, tu elección de cookies (almacenamiento local, hasta que lo borres) y prevención de fraude de Stripe (__stripe_mid, 1 año; __stripe_sid, 30 minutos).",
          "Analítica, Google Analytics: _ga y _ga_* (hasta 2 años).",
          "Marketing, Meta: _fbp y _fbc (hasta 3 meses).",
          "Marketing, TikTok: _ttp, _tt_enable_cookie y ttcsid (hasta 13 meses).",
        ],
      },
    ],
  },
  {
    heading: "Seguridad",
    body: [
      "Usamos conexión cifrada (HTTPS), contraseñas cifradas, acceso restringido al panel y proveedores de pago certificados. Si ocurriera una brecha de seguridad que afecte tus datos, te avisaremos y avisaremos a las autoridades cuando la ley lo exija.",
    ],
  },
  {
    heading: "Cambios en esta política",
    body: [
      "Si cambiamos esta política, publicaremos la nueva versión aquí con su fecha. Si el cambio es importante, te avisaremos por correo o en la web.",
    ],
  },
];

export default function PrivacyPage() {
  return <LegalPage title="Política de Privacidad y Cookies" sections={sections} updated={LEGAL.updated} />;
}

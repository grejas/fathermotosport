<?php

use App\Models\ShippingReturnsSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Política de envío y devoluciones con derecho de desistimiento (auditoría legal, 27-nov-2026).
 *
 * Antes solo se aceptaban devoluciones por daño o error, con aviso en 48 h. Eso choca con
 * derechos irrenunciables del comprador: arrepentimiento de 7 días en Brasil (CDC art. 49),
 * desistimiento de 14 días en la UE (Directiva 2011/83) y garantía legal por defectos
 * (90 días en Brasil, 2 años en la UE) que un aviso de 48 h no puede recortar.
 *
 * Política nueva, igual para todos los países: 14 días desde la entrega para desistir sin
 * motivo, formulario en /withdrawal, reembolso en 14 días, devolución a cargo del cliente
 * salvo donde la ley lo pone a cargo del vendedor (Brasil, Argentina) o si llegó mal.
 *
 * Reemplaza los textos del panel (Configuración → Envío y devoluciones); se pueden seguir
 * editando ahí. damage_report_hours ({hours}) se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = ShippingReturnsSetting::find(1);
        if (! $setting) {
            return;
        }

        $setting->update([
            'badge_returns' => [
                'es' => 'Devolución hasta 14 días después de recibirlo',
                'pt' => 'Devolução até 14 dias após o recebimento',
                'en' => 'Returns within 14 days of delivery',
            ],
            'summary' => [
                'es' => "Tienes 14 días desde que recibes tu pedido para devolverlo sin dar ningún motivo. Te devolvemos todo lo pagado, incluido el envío original.\n\nSi llega dañado o incorrecto, avísanos dentro de {hours} horas y te enviamos el artículo correcto sin costo o te devolvemos tu dinero, a tu elección. Los defectos que aparezcan después están cubiertos por la garantía legal.",
                'pt' => "Você tem 14 dias desde o recebimento do pedido para devolvê-lo sem precisar dar nenhum motivo. Devolvemos tudo o que foi pago, incluído o frete original.\n\nSe chegar danificado ou errado, avise-nos em até {hours} horas e enviamos o artigo correto sem custo ou devolvemos o seu dinheiro, você escolhe. Defeitos que aparecerem depois estão cobertos pela garantia legal.",
                'en' => "You have 14 days from receiving your order to return it without giving any reason. We refund everything you paid, including the original shipping.\n\nIf it arrives damaged or wrong, tell us within {hours} hours and we send the correct item at no cost or refund your money, your choice. Defects that appear later are covered by the legal guarantee.",
            ],
            'page_body' => [
                'es' => $this->html(
                    'Envío gratuito a toda América y Europa. Puedes devolver tu compra dentro de los 14 días siguientes a recibirla, aunque no tenga ningún defecto. Si llega dañada o incorrecta, la reponemos o te devolvemos el dinero sin costo.',
                    [
                        ['Derecho de desistimiento: 14 días', [
                            'Tienes 14 días naturales desde que recibes el pedido para desistir de la compra, sin indicar el motivo. Este plazo cumple o supera el mínimo legal de tu país (por ejemplo, 7 días en Brasil y 14 en la Unión Europea).',
                            ['Para ejercerlo, usa el formulario ', '/withdrawal', 'Desistir de una compra', ' o escríbenos por los canales del final de esta página con tu número de pedido. Te confirmamos la recepción por correo.'],
                            'Devuelve el producto dentro de los 14 días siguientes a comunicarnos tu decisión, completo, con sus accesorios y etiquetas y, si es posible, en su embalaje original.',
                            'Puedes revisar el producto como lo harías en una tienda (por ejemplo, probarte el casco para elegir la talla). Si lo usas más allá de eso, podemos descontar la pérdida de valor.',
                            'Por higiene, no se aceptan devoluciones de pasamontañas, forros interiores ni ropa interior técnica si se abrió su precinto.',
                        ]],
                        ['Reembolso y costos', [
                            'Te devolvemos todo lo pagado, incluido el envío original, dentro de los 14 días siguientes a recibir tu decisión de desistir y por el mismo medio de pago. Podemos esperar a recibir el producto o la prueba de que lo enviaste.',
                            'El costo de enviarnos el producto de vuelta corre por tu cuenta, salvo que la ley de tu país lo ponga a nuestro cargo (por ejemplo, en Brasil y Argentina) o que el producto haya llegado dañado o incorrecto.',
                        ]],
                        ['Productos dañados, defectuosos o incorrectos', [
                            'Si el producto llega dañado o incorrecto, avísanos dentro de {hours} horas con fotografías y te enviamos el artículo correcto sin costo o te devolvemos tu dinero, a tu elección.',
                            'Pasado ese plazo sigues cubierto por la garantía legal de tu país (por ejemplo, 90 días en Brasil y 2 años en la Unión Europea) y por la garantía del fabricante.',
                            'En estos casos, el envío de devolución y el de reemplazo no tienen costo para ti.',
                        ]],
                        ['Cómo funciona el proceso', [
                            'Desiste con el formulario o repórtanos el problema por los canales del final, con tu número de pedido.',
                            'Te indicamos la dirección y la forma de envío de la devolución.',
                            'Al recibir y revisar el artículo, procesamos el reembolso o el reemplazo.',
                        ], true],
                        ['Cancelaciones', [
                            'Puedes cancelar el pedido antes de que se despache, con reembolso total.',
                            'Una vez despachado, igualmente puedes desistir de la compra dentro de los 14 días desde que lo recibas.',
                        ]],
                        ['Aranceles e impuestos de importación', [
                            'Los envíos son internacionales. Los precios no incluyen los impuestos ni los aranceles de importación de tu país: si la aduana los cobra al recibir el paquete, corren por cuenta de quien recibe el pedido.',
                        ]],
                    ],
                    'es'
                ),
                'pt' => $this->html(
                    'Frete grátis para toda a América e Europa. Você pode devolver a sua compra em até 14 dias após recebê-la, mesmo sem nenhum defeito. Se chegar danificada ou errada, substituímos ou devolvemos o dinheiro sem custo.',
                    [
                        ['Direito de arrependimento: 14 dias', [
                            'Você tem 14 dias corridos desde o recebimento do pedido para desistir da compra, sem precisar dar o motivo. Esse prazo cumpre ou supera o mínimo legal do seu país (por exemplo, 7 dias no Brasil e 14 na União Europeia).',
                            ['Para exercê-lo, use o formulário ', '/pt/withdrawal', 'Desistir de uma compra', ' ou escreva para nós pelos canais no final desta página com o número do pedido. Confirmamos o recebimento por e-mail.'],
                            'Devolva o produto em até 14 dias após nos comunicar a sua decisão, completo, com acessórios e etiquetas e, se possível, na embalagem original.',
                            'Você pode examinar o produto como faria numa loja (por exemplo, provar o capacete para escolher o tamanho). Se usá-lo além disso, podemos descontar a perda de valor.',
                            'Por higiene, não aceitamos devolução de balaclavas, forros internos nem roupa íntima técnica se o lacre tiver sido aberto.',
                        ]],
                        ['Reembolso e custos', [
                            'Devolvemos tudo o que foi pago, incluído o frete original, em até 14 dias após recebermos a sua decisão de desistir e pelo mesmo meio de pagamento. Podemos aguardar o recebimento do produto ou o comprovante de envio.',
                            'O custo de nos enviar o produto de volta é do cliente, salvo quando a lei do seu país o coloca a nosso cargo (por exemplo, no Brasil e na Argentina) ou quando o produto chegou danificado ou errado.',
                        ]],
                        ['Produtos danificados, com defeito ou errados', [
                            'Se o produto chegar danificado ou errado, avise-nos em até {hours} horas com fotografias e enviamos o artigo correto sem custo ou devolvemos o seu dinheiro, você escolhe.',
                            'Depois desse prazo você continua coberto pela garantia legal do seu país (por exemplo, 90 dias no Brasil e 2 anos na União Europeia) e pela garantia do fabricante.',
                            'Nesses casos, o envio de devolução e o de substituição não têm custo para você.',
                        ]],
                        ['Como funciona o processo', [
                            'Desista pelo formulário ou reporte o problema pelos canais no final, com o número do pedido.',
                            'Informamos o endereço e a forma de envio da devolução.',
                            'Ao receber e verificar o artigo, processamos o reembolso ou a substituição.',
                        ], true],
                        ['Cancelamentos', [
                            'Você pode cancelar o pedido antes do despacho, com reembolso total.',
                            'Depois de despachado, você ainda pode desistir da compra em até 14 dias após recebê-la.',
                        ]],
                        ['Impostos e tributos de importação', [
                            'Os envios são internacionais. Os preços não incluem os impostos nem os tributos de importação do seu país: se a alfândega os cobrar no recebimento, ficam por conta de quem recebe o pedido.',
                        ]],
                    ],
                    'pt'
                ),
                'en' => $this->html(
                    'Free shipping across the Americas and Europe. You can return your purchase within 14 days of receiving it, even if nothing is wrong with it. If it arrives damaged or wrong, we replace it or refund you at no cost.',
                    [
                        ['Right of withdrawal: 14 days', [
                            'You have 14 calendar days from receiving the order to cancel the purchase without giving any reason. This meets or exceeds the legal minimum in your country (for example, 7 days in Brazil and 14 in the European Union).',
                            ['To use it, fill in the ', '/en/withdrawal', 'Withdraw from a purchase', ' form or write to us through the channels at the end of this page with your order number. We confirm receipt by email.'],
                            'Send the product back within 14 days of telling us your decision, complete, with its accessories and tags and, if possible, in its original packaging.',
                            'You may inspect the product as you would in a shop (for example, try the helmet on to check the size). If you use it beyond that, we may deduct the loss in value.',
                            'For hygiene reasons, balaclavas, inner liners and technical underwear cannot be returned once their seal has been opened.',
                        ]],
                        ['Refund and costs', [
                            'We refund everything you paid, including the original shipping, within 14 days of receiving your withdrawal, using the same payment method. We may wait until we receive the product or proof that you sent it.',
                            'The cost of sending the product back is yours, unless the law of your country places it on us (for example, Brazil and Argentina) or the product arrived damaged or wrong.',
                        ]],
                        ['Damaged, faulty or wrong products', [
                            'If the product arrives damaged or wrong, tell us within {hours} hours with photographs and we send the correct item at no cost or refund your money, your choice.',
                            'After that period you remain covered by the legal guarantee of your country (for example, 90 days in Brazil and 2 years in the European Union) and by the manufacturer\'s warranty.',
                            'In these cases, the return and replacement shipping are free for you.',
                        ]],
                        ['How the process works', [
                            'Withdraw with the form or report the issue through the channels at the end, with your order number.',
                            'We tell you the address and how to ship the return.',
                            'Once we receive and check the item, we process the refund or replacement.',
                        ], true],
                        ['Cancellations', [
                            'You can cancel the order before it is dispatched, with a full refund.',
                            'Once dispatched, you can still withdraw from the purchase within 14 days of receiving it.',
                        ]],
                        ['Import duties and taxes', [
                            'Orders ship internationally. Prices do not include your country\'s import taxes or duties: if customs charges them on delivery, they are paid by the recipient.',
                        ]],
                    ],
                    'en'
                ),
            ],
        ]);
    }

    public function down(): void
    {
        // Texto legal: se ajusta desde el panel, no se revierte solo.
    }

    /**
     * HTML como el del RichEditor de Filament. Un ítem puede ser un texto o
     * [antes, href, texto del enlace, después] para incluir un enlace.
     *
     * @param  array<int, array{0: string, 1: array<int, string|array{0: string, 1: string, 2: string, 3: string}>, 2?: bool}>  $sections
     */
    private function html(string $intro, array $sections, string $locale): string
    {
        $html = '<p>'.e($intro).'</p>';

        foreach ($sections as $section) {
            [$title, $items] = $section;
            $list = ($section[2] ?? false) ? 'ol' : 'ul';

            $html .= '<h2>'.e($title).'</h2><'.$list.'>';
            foreach ($items as $item) {
                if (is_array($item)) {
                    [$antes, $href, $texto, $despues] = $item;
                    $html .= '<li>'.e($antes).'<a href="'.e($href).'">'.e($texto).'</a>'.e($despues).'</li>';
                } else {
                    $html .= '<li>'.e($item).'</li>';
                }
            }
            $html .= '</'.$list.'>';
        }

        return $html;
    }
};

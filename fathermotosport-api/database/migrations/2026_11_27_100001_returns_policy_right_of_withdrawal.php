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
 * Política nueva (decisión de Gerson, 27-nov-2026): plazo de desistimiento = el mínimo legal de
 * cada país (lista en plazos()), formulario en /withdrawal, reembolso en 14 días y devolución a
 * cargo del cliente salvo que la ley aplicable la ponga a cargo del vendedor o si llegó mal.
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
                'es' => 'Desistimiento según la ley de tu país',
                'pt' => 'Arrependimento conforme a lei do seu país',
                'en' => 'Right of withdrawal under your country\'s law',
            ],
            'summary' => [
                'es' => "Puedes desistir de tu compra sin dar motivo dentro del plazo que fija la ley de tu país, contado desde la entrega (por ejemplo, 14 días en la Unión Europea, 10 en Argentina y Chile y 7 en Brasil). Te devolvemos todo lo pagado, incluido el envío original.\n\nSi llega dañado o incorrecto, avísanos dentro de {hours} horas y te enviamos el artículo correcto sin costo o te devolvemos tu dinero, a tu elección. Los defectos que aparezcan después están cubiertos por la garantía legal.",
                'pt' => "Você pode desistir da compra sem dar motivo dentro do prazo fixado pela lei do seu país, contado a partir da entrega (por exemplo, 7 dias no Brasil, 10 na Argentina e no Chile e 14 na União Europeia). Devolvemos tudo o que foi pago, incluído o frete original.\n\nSe chegar danificado ou errado, avise-nos em até {hours} horas e enviamos o artigo correto sem custo ou devolvemos o seu dinheiro, você escolhe. Defeitos que aparecerem depois estão cobertos pela garantia legal.",
                'en' => "You can withdraw from your purchase without giving a reason within the period set by the law of your country, counted from delivery (for example, 14 days in the European Union and the United Kingdom). We refund everything you paid, including the original shipping.\n\nIf it arrives damaged or wrong, tell us within {hours} hours and we send the correct item at no cost or refund your money, your choice. Defects that appear later are covered by the legal guarantee.",
            ],
            'page_body' => [
                'es' => $this->body('es'),
                'pt' => $this->body('pt'),
                'en' => $this->body('en'),
            ],
        ]);
    }

    /**
     * Plazos legales de desistimiento por país, contados desde la entrega (verificados el
     * 27-nov-2026). Países sin plazo legal general (EE.UU., Canadá, Suiza, Panamá, Bolivia y
     * otros) quedan cubiertos por la cláusula de cierre: si su ley da un plazo, se respeta.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>  [es, pt, en]
     */
    private function plazos(): array
    {
        return [
            ['Unión Europea, Noruega y Reino Unido: 14 días naturales', 'União Europeia, Noruega e Reino Unido: 14 dias corridos', 'European Union, Norway and United Kingdom: 14 calendar days'],
            ['Argentina y Chile: 10 días corridos', 'Argentina e Chile: 10 dias corridos', 'Argentina and Chile: 10 calendar days'],
            ['Costa Rica y El Salvador: 8 días', 'Costa Rica e El Salvador: 8 dias', 'Costa Rica and El Salvador: 8 days'],
            ['Brasil y Perú: 7 días', 'Brasil e Peru: 7 dias', 'Brazil and Peru: 7 days'],
            ['Colombia, México, Uruguay y Nicaragua: 5 días hábiles', 'Colômbia, México, Uruguai e Nicarágua: 5 dias úteis', 'Colombia, Mexico, Uruguay and Nicaragua: 5 business days'],
            ['Ecuador: 3 días', 'Equador: 3 dias', 'Ecuador: 3 days'],
        ];
    }

    private function body(string $l): string
    {
        $i = ['es' => 0, 'pt' => 1, 'en' => 2][$l];
        $plazos = array_map(fn ($p) => $p[$i], $this->plazos());
        $form = ['es' => '/withdrawal', 'pt' => '/pt/withdrawal', 'en' => '/en/withdrawal'][$l];

        $t = [
            'es' => [
                'intro' => 'Envío gratuito a toda América y Europa. Puedes desistir de tu compra sin dar motivo dentro del plazo que te reconoce la ley de tu país. Si llega dañada o incorrecta, la reponemos o te devolvemos el dinero sin costo.',
                'h1' => 'Derecho de desistimiento',
                'l1' => 'Puedes desistir de la compra sin indicar el motivo dentro de estos plazos, contados desde que recibes el pedido:',
                'l2' => 'En los demás países, si la ley de tu país te reconoce un plazo para desistir, lo respetamos.',
                'link' => ['Para ejercerlo, usa el formulario ', 'Desistir de una compra', ' o escríbenos por los canales del final de esta página con tu número de pedido. Te confirmamos la recepción por correo.'],
                'l3' => 'Devuelve el producto dentro de los 14 días siguientes a comunicarnos tu decisión, completo, con sus accesorios y etiquetas y, si es posible, en su embalaje original.',
                'l4' => 'Puedes revisar el producto como lo harías en una tienda (por ejemplo, probarte el casco para elegir la talla). Si lo usas más allá de eso, podemos descontar la pérdida de valor.',
                'l5' => 'Por higiene, no se aceptan devoluciones de pasamontañas, forros interiores ni ropa interior técnica si se abrió su precinto.',
                'h2' => 'Reembolso y costos',
                'r1' => 'Te devolvemos todo lo pagado, incluido el envío original, dentro de los 14 días siguientes a recibir tu decisión de desistir y por el mismo medio de pago. Podemos esperar a recibir el producto o la prueba de que lo enviaste.',
                'r2' => 'El costo de enviarnos el producto de vuelta corre por cuenta del cliente, salvo que la ley aplicable lo ponga a cargo del vendedor o que el producto haya llegado dañado o incorrecto.',
                'h3' => 'Productos dañados, defectuosos o incorrectos',
                'd1' => 'Si el producto llega dañado o incorrecto, avísanos dentro de {hours} horas con fotografías y te enviamos el artículo correcto sin costo o te devolvemos tu dinero, a tu elección.',
                'd2' => 'Pasado ese plazo sigues cubierto por la garantía legal de tu país y por la garantía del fabricante.',
                'd3' => 'En estos casos, el envío de devolución y el de reemplazo no tienen costo para ti.',
                'h4' => 'Cómo funciona el proceso',
                'p1' => 'Desiste con el formulario o repórtanos el problema por los canales del final, con tu número de pedido.',
                'p2' => 'Te indicamos la dirección y la forma de envío de la devolución.',
                'p3' => 'Al recibir y revisar el artículo, procesamos el reembolso o el reemplazo.',
                'h5' => 'Cancelaciones',
                'c1' => 'Puedes cancelar el pedido antes de que se despache, con reembolso total.',
                'c2' => 'Una vez despachado, igualmente puedes desistir dentro del plazo de tu país, contado desde que lo recibas.',
                'h6' => 'Aranceles e impuestos de importación',
                'a1' => 'Los envíos son internacionales. Los precios no incluyen los impuestos ni los aranceles de importación de tu país: si la aduana los cobra al recibir el paquete, corren por cuenta de quien recibe el pedido.',
            ],
            'pt' => [
                'intro' => 'Frete grátis para toda a América e Europa. Você pode desistir da compra sem dar motivo dentro do prazo que a lei do seu país lhe garante. Se chegar danificada ou errada, substituímos ou devolvemos o dinheiro sem custo.',
                'h1' => 'Direito de arrependimento',
                'l1' => 'Você pode desistir da compra sem dar o motivo dentro destes prazos, contados a partir do recebimento do pedido:',
                'l2' => 'Nos demais países, se a lei do seu país garantir um prazo para desistir, nós o respeitamos.',
                'link' => ['Para exercê-lo, use o formulário ', 'Desistir de uma compra', ' ou escreva para nós pelos canais no final desta página com o número do pedido. Confirmamos o recebimento por e-mail.'],
                'l3' => 'Devolva o produto em até 14 dias após nos comunicar a sua decisão, completo, com acessórios e etiquetas e, se possível, na embalagem original.',
                'l4' => 'Você pode examinar o produto como faria numa loja (por exemplo, provar o capacete para escolher o tamanho). Se usá-lo além disso, podemos descontar a perda de valor.',
                'l5' => 'Por higiene, não aceitamos devolução de balaclavas, forros internos nem roupa íntima técnica se o lacre tiver sido aberto.',
                'h2' => 'Reembolso e custos',
                'r1' => 'Devolvemos tudo o que foi pago, incluído o frete original, em até 14 dias após recebermos a sua decisão de desistir e pelo mesmo meio de pagamento. Podemos aguardar o recebimento do produto ou o comprovante de envio.',
                'r2' => 'O custo de nos enviar o produto de volta é do cliente, salvo quando a lei aplicável o colocar a cargo do vendedor ou quando o produto chegou danificado ou errado.',
                'h3' => 'Produtos danificados, com defeito ou errados',
                'd1' => 'Se o produto chegar danificado ou errado, avise-nos em até {hours} horas com fotografias e enviamos o artigo correto sem custo ou devolvemos o seu dinheiro, você escolhe.',
                'd2' => 'Depois desse prazo você continua coberto pela garantia legal do seu país e pela garantia do fabricante.',
                'd3' => 'Nesses casos, o envio de devolução e o de substituição não têm custo para você.',
                'h4' => 'Como funciona o processo',
                'p1' => 'Desista pelo formulário ou reporte o problema pelos canais no final, com o número do pedido.',
                'p2' => 'Informamos o endereço e a forma de envio da devolução.',
                'p3' => 'Ao receber e verificar o artigo, processamos o reembolso ou a substituição.',
                'h5' => 'Cancelamentos',
                'c1' => 'Você pode cancelar o pedido antes do despacho, com reembolso total.',
                'c2' => 'Depois de despachado, você ainda pode desistir dentro do prazo do seu país, contado a partir do recebimento.',
                'h6' => 'Impostos e tributos de importação',
                'a1' => 'Os envios são internacionais. Os preços não incluem os impostos nem os tributos de importação do seu país: se a alfândega os cobrar no recebimento, ficam por conta de quem recebe o pedido.',
            ],
            'en' => [
                'intro' => 'Free shipping across the Americas and Europe. You can withdraw from your purchase without giving a reason within the period your country\'s law grants you. If it arrives damaged or wrong, we replace it or refund you at no cost.',
                'h1' => 'Right of withdrawal',
                'l1' => 'You can withdraw from the purchase without giving a reason within these periods, counted from when you receive the order:',
                'l2' => 'In other countries, if your country\'s law grants a withdrawal period, we honour it.',
                'link' => ['To use it, fill in the ', 'Withdraw from a purchase', ' form or write to us through the channels at the end of this page with your order number. We confirm receipt by email.'],
                'l3' => 'Send the product back within 14 days of telling us your decision, complete, with its accessories and tags and, if possible, in its original packaging.',
                'l4' => 'You may inspect the product as you would in a shop (for example, try the helmet on to check the size). If you use it beyond that, we may deduct the loss in value.',
                'l5' => 'For hygiene reasons, balaclavas, inner liners and technical underwear cannot be returned once their seal has been opened.',
                'h2' => 'Refund and costs',
                'r1' => 'We refund everything you paid, including the original shipping, within 14 days of receiving your withdrawal, using the same payment method. We may wait until we receive the product or proof that you sent it.',
                'r2' => 'The cost of sending the product back is paid by the customer, unless the applicable law places it on the seller or the product arrived damaged or wrong.',
                'h3' => 'Damaged, faulty or wrong products',
                'd1' => 'If the product arrives damaged or wrong, tell us within {hours} hours with photographs and we send the correct item at no cost or refund your money, your choice.',
                'd2' => 'After that period you remain covered by the legal guarantee of your country and by the manufacturer\'s warranty.',
                'd3' => 'In these cases, the return and replacement shipping are free for you.',
                'h4' => 'How the process works',
                'p1' => 'Withdraw with the form or report the issue through the channels at the end, with your order number.',
                'p2' => 'We tell you the address and how to ship the return.',
                'p3' => 'Once we receive and check the item, we process the refund or replacement.',
                'h5' => 'Cancellations',
                'c1' => 'You can cancel the order before it is dispatched, with a full refund.',
                'c2' => 'Once dispatched, you can still withdraw within your country\'s period, counted from when you receive it.',
                'h6' => 'Import duties and taxes',
                'a1' => 'Orders ship internationally. Prices do not include your country\'s import taxes or duties: if customs charges them on delivery, they are paid by the recipient.',
            ],
        ][$l];

        $li = fn (string $x) => '<li>'.e($x).'</li>';
        [$antes, $texto, $despues] = $t['link'];

        return '<p>'.e($t['intro']).'</p>'
            .'<h2>'.e($t['h1']).'</h2>'
            .'<p>'.e($t['l1']).'</p><ul>'.implode('', array_map($li, $plazos)).'</ul>'
            .'<ul>'.$li($t['l2'])
            .'<li>'.e($antes).'<a href="'.e($form).'">'.e($texto).'</a>'.e($despues).'</li>'
            .$li($t['l3']).$li($t['l4']).$li($t['l5']).'</ul>'
            .'<h2>'.e($t['h2']).'</h2><ul>'.$li($t['r1']).$li($t['r2']).'</ul>'
            .'<h2>'.e($t['h3']).'</h2><ul>'.$li($t['d1']).$li($t['d2']).$li($t['d3']).'</ul>'
            .'<h2>'.e($t['h4']).'</h2><ol>'.$li($t['p1']).$li($t['p2']).$li($t['p3']).'</ol>'
            .'<h2>'.e($t['h5']).'</h2><ul>'.$li($t['c1']).$li($t['c2']).'</ul>'
            .'<h2>'.e($t['h6']).'</h2><ul>'.$li($t['a1']).'</ul>';
    }

    public function down(): void
    {
        // Texto legal: se ajusta desde el panel, no se revierte solo.
    }
};

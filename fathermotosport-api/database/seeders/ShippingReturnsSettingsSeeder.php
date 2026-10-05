<?php

namespace Database\Seeders;

use App\Models\ShippingReturnsSetting;
use Illuminate\Database\Seeder;

class ShippingReturnsSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: un db:seed nunca pisa lo que ya se editó desde el panel.
        ShippingReturnsSetting::firstOrCreate(['id' => 1], static::defaults());
    }

    /**
     * Textos vigentes del sitio (messages/*.json de la web) como valores iniciales,
     * para que el panel muestre lo que hoy se publica. Los plazos van como
     * {hours} y {days}: se reemplazan con damage_report_hours / withdrawal_days.
     */
    public static function defaults(): array
    {
        return [
            'damage_report_hours' => 48,
            'withdrawal_days' => 7,

            'badge_shipping' => [
                'es' => 'Envío gratuito',
                'pt' => 'Frete grátis',
                'en' => 'Free shipping',
            ],
            'badge_returns' => [
                'es' => 'Devolución gratuita',
                'pt' => 'Devolução grátis',
                'en' => 'Free returns',
            ],
            'summary_shipping' => [
                'es' => 'Envío gratuito a toda América y Europa.',
                'pt' => 'Frete grátis para toda a América e Europa.',
                'en' => 'Free shipping across the Americas and Europe.',
            ],
            'summary_damaged' => [
                'es' => 'Si tu artículo llega dañado, tienes {hours} horas desde la entrega para reportarlo. Te enviamos uno nuevo sin costo o te devolvemos tu dinero, a tu elección.',
                'pt' => 'Se o seu artigo chegar danificado, você tem {hours} horas desde a entrega para reportar. Enviamos um novo sem custo ou devolvemos o seu dinheiro, você escolhe.',
                'en' => 'If your item arrives damaged, you have {hours} hours from delivery to report it. We send you a new one at no cost or refund your money, your choice.',
            ],
            'summary_withdrawal' => [
                'es' => 'También puedes devolver cualquier artículo dentro de los {days} días posteriores a la entrega, sin necesidad de que esté dañado, siempre que esté sin usar, sin daños y completo, con su empaque original y todos sus accesorios.',
                'pt' => 'Você também pode devolver qualquer artigo dentro de {days} dias após a entrega, mesmo que não esteja danificado, desde que esteja sem uso, sem danos e completo, com a embalagem original e todos os seus acessórios.',
                'en' => 'You can also return any item within {days} days of delivery, even if it is not damaged, as long as it is unused, undamaged and complete, with its original packaging and all its accessories.',
            ],

            'page_title' => [
                'es' => 'Política de Envío y Devoluciones',
                'pt' => 'Política de Frete e Devoluções',
                'en' => 'Shipping and Returns Policy',
            ],
            'page_intro' => [
                'es' => 'Envío gratuito a toda América y Europa. A continuación se detallan las condiciones de devolución, reemplazo y cancelación de pedidos.',
                'pt' => 'Frete grátis para toda a América e Europa. A seguir, as condições de devolução, substituição e cancelamento de pedidos.',
                'en' => 'Free shipping across the Americas and Europe. The conditions for returns, replacements and order cancellations are set out below.',
            ],
            'damaged_title' => [
                'es' => 'Devoluciones por artículo dañado',
                'pt' => 'Devoluções por artigo danificado',
                'en' => 'Returns for damaged items',
            ],
            'damaged_items' => [
                'es' => [
                    'El plazo para reportar un artículo dañado o defectuoso es de {hours} horas desde la entrega.',
                    'El reporte debe incluir fotografías del daño.',
                    'El cliente elige entre el envío de un artículo nuevo sin costo o el reembolso íntegro de su dinero.',
                    'El envío de reemplazo y la devolución del artículo dañado no tienen costo para el cliente.',
                ],
                'pt' => [
                    'O prazo para reportar um artigo danificado ou com defeito é de {hours} horas desde a entrega.',
                    'O relato deve incluir fotografias do dano.',
                    'O cliente escolhe entre o envio de um artigo novo sem custo ou o reembolso integral do seu dinheiro.',
                    'O envio de substituição e a devolução do artigo danificado não têm custo para o cliente.',
                ],
                'en' => [
                    'Damaged or defective items must be reported within {hours} hours of delivery.',
                    'The report must include photographs of the damage.',
                    'The customer chooses between a replacement item at no cost or a full refund.',
                    'Both the replacement shipment and the return of the damaged item are free for the customer.',
                ],
            ],
            'withdrawal_title' => [
                'es' => 'Derecho de arrepentimiento',
                'pt' => 'Direito de arrependimento',
                'en' => 'Right to change your mind',
            ],
            'withdrawal_items' => [
                'es' => [
                    'El plazo para solicitar una devolución sin justificar el motivo es de {days} días desde la entrega del pedido.',
                    'El artículo debe estar sin usar y sin daños atribuibles al cliente.',
                    'La devolución debe ser completa: empaque original y todos los accesorios y componentes incluidos.',
                    'Los artículos que no cumplan estas condiciones no califican para el reembolso.',
                ],
                'pt' => [
                    'O prazo para solicitar uma devolução sem justificar o motivo é de {days} dias desde a entrega do pedido.',
                    'O artigo deve estar sem uso e sem danos atribuíveis ao cliente.',
                    'A devolução deve ser completa: embalagem original e todos os acessórios e componentes incluídos.',
                    'Artigos que não cumpram essas condições não se qualificam para o reembolso.',
                ],
                'en' => [
                    'Returns with no reason given may be requested within {days} days of delivery.',
                    'The item must be unused and free of any damage caused by the customer.',
                    'The return must be complete: original packaging and every accessory and component included.',
                    'Items that do not meet these conditions do not qualify for a refund.',
                ],
            ],
            'process_title' => [
                'es' => 'Cómo funciona el proceso',
                'pt' => 'Como funciona o processo',
                'en' => 'How the process works',
            ],
            'process_items' => [
                'es' => [
                    'Solicita la devolución por los canales indicados al final de esta página, con tu número de pedido y el motivo.',
                    'Coordinamos la recolección o el envío de devolución.',
                    'Una vez recibido y verificado el artículo, se procesa el reembolso o el reemplazo.',
                ],
                'pt' => [
                    'Solicite a devolução pelos canais indicados no final desta página, com o número do pedido e o motivo.',
                    'Combinamos a coleta ou o envio de devolução.',
                    'Assim que o artigo for recebido e verificado, o reembolso ou a substituição é processado.',
                ],
                'en' => [
                    'Request the return through the channels listed at the end of this page, with your order number and the reason.',
                    'We arrange the pickup or the return shipment.',
                    'Once the item is received and checked, the refund or replacement is processed.',
                ],
            ],
            'cancellations_title' => [
                'es' => 'Cancelaciones',
                'pt' => 'Cancelamentos',
                'en' => 'Cancellations',
            ],
            'cancellations_items' => [
                'es' => [
                    'El pedido puede cancelarse en cualquier momento antes de que comience a procesarse para su envío.',
                    'Si el pedido ya fue despachado, se aplica el proceso de devolución descrito arriba.',
                ],
                'pt' => [
                    'O pedido pode ser cancelado a qualquer momento antes de começar a ser preparado para envio.',
                    'Se o pedido já foi despachado, aplica-se o processo de devolução descrito acima.',
                ],
                'en' => [
                    'An order may be cancelled at any time before it starts being processed for shipping.',
                    'If the order has already been dispatched, the return process described above applies.',
                ],
            ],
            'help_text' => [
                'es' => 'Para iniciar una devolución o consultar por tu pedido, escríbenos por cualquiera de estos canales:',
                'pt' => 'Para iniciar uma devolução ou consultar sobre o seu pedido, fale conosco por um destes canais:',
                'en' => 'To start a return or ask about your order, contact us through either of these channels:',
            ],
        ];
    }
}

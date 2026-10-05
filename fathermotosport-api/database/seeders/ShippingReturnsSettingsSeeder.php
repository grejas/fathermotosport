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
     * para que el panel muestre lo que hoy se publica. El cuerpo de la política va
     * en HTML del RichEditor; el plazo va como {hours} y se reemplaza con
     * damage_report_hours.
     */
    public static function defaults(): array
    {
        return [
            'damage_report_hours' => 48,

            'badge_returns' => [
                'es' => 'Devolución gratuita si llega dañado o incorrecto',
                'pt' => 'Devolução grátis se chegar danificado ou errado',
                'en' => 'Free returns if it arrives damaged or wrong',
            ],
            'summary' => [
                'es' => "Si tu artículo llega defectuoso, dañado o no corresponde a lo que pediste, tienes {hours} horas desde la entrega para reportarlo. Te enviamos el artículo correcto sin costo o te devolvemos tu dinero, a tu elección.\n\nSolo aceptamos devoluciones en esos casos: no se aceptan devoluciones por cambio de opinión ni por otros motivos.",
                'pt' => "Se o seu artigo chegar com defeito, danificado ou não corresponder ao que você pediu, você tem {hours} horas desde a entrega para reportar. Enviamos o artigo correto sem custo ou devolvemos o seu dinheiro, você escolhe.\n\nSó aceitamos devoluções nesses casos: não são aceitas devoluções por arrependimento nem por outros motivos.",
                'en' => "If your item arrives faulty, damaged or not matching what you ordered, you have {hours} hours from delivery to report it. We send you the correct item at no cost or refund your money, your choice.\n\nReturns are only accepted in these cases: we do not accept returns for a change of mind or for any other reason.",
            ],
            'page_title' => [
                'es' => 'Política de Envío y Devoluciones',
                'pt' => 'Política de Frete e Devoluções',
                'en' => 'Shipping and Returns Policy',
            ],
            'page_body' => [
                'es' => static::html(
                    'Envío gratuito a toda América y Europa. Aceptamos devoluciones únicamente cuando el producto llega defectuoso, dañado, incorrecto o no corresponde a lo pedido. A continuación se detallan las condiciones de reemplazo, reembolso y cancelación de pedidos.',
                    [
                        ['Cuándo aceptamos devoluciones', [
                            'Solo aceptamos devoluciones si el producto llega defectuoso, dañado, incorrecto o no corresponde a lo que pediste.',
                            'El plazo para reportarlo es de {hours} horas desde la entrega.',
                            'El reporte debe incluir fotografías del producto recibido y del problema.',
                            'El cliente elige entre el envío del artículo correcto sin costo o el reembolso íntegro de su dinero.',
                            'El envío de reemplazo y la devolución del artículo no tienen costo para el cliente.',
                        ]],
                        ['Devoluciones que no aceptamos', [
                            'No se aceptan devoluciones por cambio de opinión ni por motivos distintos a los indicados arriba.',
                            'Los reclamos realizados después de las {hours} horas desde la entrega no califican para reemplazo ni reembolso.',
                        ]],
                        ['Cómo funciona el proceso', [
                            'Reporta el problema por los canales indicados al final de esta página, con tu número de pedido, el motivo y las fotografías.',
                            'Coordinamos la recolección o el envío de devolución.',
                            'Una vez recibido y verificado el artículo, se procesa el reembolso o el reemplazo.',
                        ], true],
                        ['Cancelaciones', [
                            'El pedido puede cancelarse en cualquier momento antes de que comience a procesarse para su envío.',
                            'Una vez despachado, el pedido ya no puede cancelarse: solo se aceptan devoluciones en los casos descritos arriba.',
                        ]],
                    ]
                ),
                'pt' => static::html(
                    'Frete grátis para toda a América e Europa. Aceitamos devoluções somente quando o produto chega com defeito, danificado, errado ou não corresponde ao pedido. A seguir, as condições de substituição, reembolso e cancelamento de pedidos.',
                    [
                        ['Quando aceitamos devoluções', [
                            'Só aceitamos devoluções se o produto chegar com defeito, danificado, errado ou não corresponder ao que você pediu.',
                            'O prazo para reportar é de {hours} horas desde a entrega.',
                            'O relato deve incluir fotografias do produto recebido e do problema.',
                            'O cliente escolhe entre o envio do artigo correto sem custo ou o reembolso integral do seu dinheiro.',
                            'O envio de substituição e a devolução do artigo não têm custo para o cliente.',
                        ]],
                        ['Devoluções que não aceitamos', [
                            'Não são aceitas devoluções por arrependimento nem por motivos diferentes dos indicados acima.',
                            'Reclamações feitas depois de {hours} horas desde a entrega não se qualificam para substituição nem reembolso.',
                        ]],
                        ['Como funciona o processo', [
                            'Reporte o problema pelos canais indicados no final desta página, com o número do pedido, o motivo e as fotografias.',
                            'Combinamos a coleta ou o envio de devolução.',
                            'Assim que o artigo for recebido e verificado, o reembolso ou a substituição é processado.',
                        ], true],
                        ['Cancelamentos', [
                            'O pedido pode ser cancelado a qualquer momento antes de começar a ser preparado para envio.',
                            'Depois de despachado, o pedido não pode mais ser cancelado: só são aceitas devoluções nos casos descritos acima.',
                        ]],
                    ]
                ),
                'en' => static::html(
                    'Free shipping across the Americas and Europe. Returns are accepted only when the product arrives faulty, damaged, wrong or not matching the order. The conditions for replacements, refunds and order cancellations are set out below.',
                    [
                        ['When we accept returns', [
                            'Returns are accepted only if the product arrives faulty, damaged, wrong or not matching what you ordered.',
                            'The issue must be reported within {hours} hours of delivery.',
                            'The report must include photographs of the product received and of the issue.',
                            'The customer chooses between the correct item at no cost or a full refund.',
                            'Both the replacement shipment and the return of the item are free for the customer.',
                        ]],
                        ['Returns we do not accept', [
                            'Returns for a change of mind, or for any reason other than those listed above, are not accepted.',
                            'Claims made more than {hours} hours after delivery do not qualify for a replacement or a refund.',
                        ]],
                        ['How the process works', [
                            'Report the issue through the channels listed at the end of this page, with your order number, the reason and the photographs.',
                            'We arrange the pickup or the return shipment.',
                            'Once the item is received and checked, the refund or replacement is processed.',
                        ], true],
                        ['Cancellations', [
                            'An order may be cancelled at any time before it starts being processed for shipping.',
                            'Once dispatched, the order can no longer be cancelled: returns are only accepted in the cases described above.',
                        ]],
                    ]
                ),
            ],
        ];
    }

    /**
     * Arma el cuerpo en el HTML que produce el RichEditor de Filament: un párrafo
     * de intro y, por sección, un <h2> con su lista (<ol> si los pasos van numerados).
     *
     * @param  array<int, array{0: string, 1: array<int, string>, 2?: bool}>  $sections
     */
    private static function html(string $intro, array $sections): string
    {
        $html = '<p>'.e($intro).'</p>';

        foreach ($sections as $section) {
            [$title, $items] = $section;
            $list = ($section[2] ?? false) ? 'ol' : 'ul';

            $html .= '<h2>'.e($title).'</h2><'.$list.'>';
            foreach ($items as $item) {
                $html .= '<li>'.e($item).'</li>';
            }
            $html .= '</'.$list.'>';
        }

        return $html;
    }
}

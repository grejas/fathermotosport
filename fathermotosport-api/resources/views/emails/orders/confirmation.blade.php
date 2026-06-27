@component('mail::message')
# ¡Gracias por tu compra!

Hemos recibido tu pedido **{{ $order->order_number }}** y lo estamos preparando.

@component('mail::table')
| Producto | Cant. | Precio |
|:---------|:-----:|-------:|
@foreach($order->items as $item)
| {{ optional(optional($item->variant)->product)->name ?? 'Producto' }} {{ $item->size ? '('.$item->size.($item->color ? ' / '.$item->color : '').')' : '' }} | {{ $item->quantity }} | ${{ number_format($item->subtotal, 2) }} |
@endforeach
@endcomponent

**Subtotal:** ${{ number_format($order->subtotal, 2) }}
@if($order->discount > 0)
**Descuento:** -${{ number_format($order->discount, 2) }}
@endif
**Envío:** Gratis
**Total:** ${{ number_format($order->total, 2) }}

Te avisaremos por este medio cuando tu pedido sea despachado.

Gracias por confiar en nosotros,
**El equipo de FatherMotoSport**
@endcomponent

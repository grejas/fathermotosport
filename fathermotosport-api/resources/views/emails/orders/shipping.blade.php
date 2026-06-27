@component('mail::message')
# ¡Tu pedido va en camino! 🏍️

Tu pedido **{{ $order->order_number }}** ha sido despachado.

@if($trackingNumber)
@component('mail::panel')
**Número de seguimiento:** {{ $trackingNumber }}
@endcomponent
@endif

Llegará a: {{ optional($order->address)->city }}, {{ optional($order->address)->country }}.

Gracias por tu compra,
**El equipo de FatherMotoSport**
@endcomponent

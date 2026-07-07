@extends('emails.layouts.base')

@section('emoji', '🚚')

@section('content')
  <div class="badge" style="background:rgba(255,122,0,0.12); border-color:rgba(255,122,0,0.3); color:#FF7A00"><span class="badge-dot" style="background:#FF7A00"></span> En camino</div>
  <div class="greeting">Hola,</div>
  <div class="title">Tu pedido {{ $order->order_number }} fue despachado</div>
  <div class="text">Tu pedido ya está en camino. Podés seguirlo con el número de tracking:</div>

  <div class="box">
    <div class="row"><span class="row-k">N° de seguimiento</span><span class="row-v">{{ $trackingNumber }}</span></div>
    @if($carrier)
      <div class="row"><span class="row-k">Transportista</span><span class="row-v">{{ $carrier }}</span></div>
    @endif
    @if($order->address)
      <div class="row"><span class="row-k">Destino</span><span class="row-v">{{ $order->address->city }}, {{ $order->address->country }}</span></div>
    @endif
  </div>

  <div class="steps">
    <div class="step"><div class="step-dot step-done">✓</div><div class="step-label">Pagado</div></div>
    <div class="step"><div class="step-dot step-done">✓</div><div class="step-label">Preparado</div></div>
    <div class="step"><div class="step-dot step-current">3</div><div class="step-label">En camino</div></div>
    <div class="step"><div class="step-dot step-pending">4</div><div class="step-label">Entregado</div></div>
  </div>

  <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="btn">Seguir mi pedido</a>
@endsection

@extends('emails.layouts.base')

@section('emoji', '🧾')

@section('content')
  <div class="badge"><span class="badge-dot"></span> Pendiente de pago</div>
  <div class="greeting">Hola{{ $user?->first_name ? ' ' . $user->first_name : '' }},</div>
  <div class="title">Recibimos tu pedido {{ $order->order_number }}</div>
  <div class="text">
    Todavía no registramos el pago. Apenas se confirme, te enviamos otro correo y preparamos tu pedido.
    Si ya pagaste, puede demorar unos minutos en acreditarse.
  </div>

  <div class="box">
    @foreach($order->items as $item)
      @php $product = optional(optional($item->variant)->product); @endphp
      <div class="product-row">
        <div class="product-icon">🏍️</div>
        <div>
          <div class="product-name">{{ $product->name ?? 'Producto' }}</div>
          <div class="product-meta">
            {{ collect([$item->size, $item->color])->filter()->implode(' · ') }} · x{{ $item->quantity }}
          </div>
        </div>
        <div class="product-price">${{ number_format($item->subtotal, 2) }}</div>
      </div>
    @endforeach

    <div class="divider"></div>
    <div class="row"><span class="row-k">Subtotal</span><span class="row-v">${{ number_format($order->subtotal, 2) }}</span></div>
    @if($order->discount > 0)
      <div class="row"><span class="row-k">Descuento</span><span class="row-v">-${{ number_format($order->discount, 2) }}</span></div>
    @endif
    <div class="row">
      <span class="row-k">Envío{{ $order->shipping_method_name ? ' · ' . $order->shipping_method_name : '' }}</span>
      <span class="row-v">{{ $order->shipping > 0 ? '$' . number_format($order->shipping, 2) : 'Gratis' }}</span>
    </div>
    <div class="total-row"><span class="total-k">Total</span><span class="total-v">${{ number_format($order->total, 2) }}</span></div>
  </div>

  @if($order->address)
    <div class="box">
      <div class="row"><span class="row-k">Entregar a</span><span class="row-v">{{ $order->address->full_name }}</span></div>
      <div class="row"><span class="row-k">Dirección</span><span class="row-v">{{ $order->address->address_line }}</span></div>
      <div class="row"><span class="row-k">Ciudad</span><span class="row-v">{{ $order->address->city }}, {{ $order->address->country }}</span></div>
    </div>
  @endif

  <div class="steps">
    <div class="step"><div class="step-dot step-current">1</div><div class="step-label">Pago pendiente</div></div>
    <div class="step"><div class="step-dot step-pending">2</div><div class="step-label">Preparando</div></div>
    <div class="step"><div class="step-dot step-pending">3</div><div class="step-label">Enviado</div></div>
    <div class="step"><div class="step-dot step-pending">4</div><div class="step-label">Entregado</div></div>
  </div>

  <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="btn">Ver mi pedido</a>
@endsection

@extends('emails.layouts.base')

@section('emoji', '🧾')

@section('content')
  <div class="badge"><span class="badge-dot"></span> Pendiente de pago</div>
  <div class="greeting">Hola{{ $user?->first_name ? ' ' . $user->first_name : '' }},</div>
  <div class="title">Recibimos tu pedido {{ $order->order_number }}</div>
  @if(!empty($pago))
    <div class="text">
      Para completar tu compra, envía el monto exacto con estos datos. Apenas confirmemos el ingreso
      te enviamos otro correo y preparamos tu pedido.
    </div>

    <div class="box">
      <div class="row"><span class="row-k">Monto exacto</span><span class="row-v"><strong>{{ $pago['amount'] }} {{ $pago['currency'] }}</strong></span></div>
      @if($pago['method'] === 'sepa')
        <div class="row"><span class="row-k">Titular</span><span class="row-v">{{ $pago['beneficiary'] }}</span></div>
        <div class="row"><span class="row-k">IBAN</span><span class="row-v" style="font-family:monospace">{{ $pago['iban'] }}</span></div>
        @if(!empty($pago['bic']))
          <div class="row"><span class="row-k">BIC / SWIFT</span><span class="row-v" style="font-family:monospace">{{ $pago['bic'] }}</span></div>
        @endif
        @if(!empty($pago['bank_name']))
          <div class="row"><span class="row-k">Banco</span><span class="row-v">{{ $pago['bank_name'] }}</span></div>
        @endif
        <div class="row"><span class="row-k">Concepto</span><span class="row-v"><strong>{{ $pago['reference'] }}</strong></span></div>
      @elseif($pago['method'] === 'binance_pay')
        <div class="row"><span class="row-k">Pagar con</span><span class="row-v"><strong>Binance Pay</strong></span></div>
        @if(!empty($pago['merchant_name']))
          <div class="row"><span class="row-k">Cuenta</span><span class="row-v">{{ $pago['merchant_name'] }}</span></div>
        @endif
        <div class="row"><span class="row-k">Enlace de pago</span><span class="row-v" style="word-break:break-all"><a href="{{ $pago['pay_url'] }}">{{ $pago['pay_url'] }}</a></span></div>
      @else
        <div class="row"><span class="row-k">Red</span><span class="row-v"><strong>{{ $pago['network'] }}</strong></span></div>
        <div class="row"><span class="row-k">Dirección</span><span class="row-v" style="font-family:monospace;word-break:break-all">{{ $pago['address'] }}</span></div>
      @endif
      @if(!empty($pago['expires_at']))
        <div class="row"><span class="row-k">Válido hasta</span><span class="row-v">{{ \Illuminate\Support\Carbon::parse($pago['expires_at'])->utc()->format('d/m/Y H:i') }} UTC</span></div>
      @endif
    </div>

    <div class="text" style="font-size:13px">
      @if($pago['method'] === 'sepa')
        Usa el número de pedido <strong>{{ $pago['reference'] }}</strong> como concepto: así identificamos tu pago.
        Una transferencia SEPA suele acreditarse en 1 día hábil (al instante si tu banco ofrece SEPA Instant).
      @elseif($pago['method'] === 'binance_pay')
        Abre el enlace o el código QR de la página de pago con la app de Binance y envía <strong>{{ $pago['amount'] }} USDT</strong>
        (o el equivalente en USDC o BTC) a <strong>{{ $pago['merchant_name'] ?: 'nuestra cuenta' }}</strong>. No necesitas elegir red.
      @else
        Envía solo por la red <strong>{{ $pago['network'] }}</strong>: un envío por otra red se pierde y no se puede recuperar.
        @if($pago['method'] === 'btc_bep20')
          Es Bitcoin como token en BNB Smart Chain (BTCB): en tu exchange elige retirar BTC por la red BSC / BEP20, <strong>no</strong> por la red Bitcoin.
        @endif
        Si tu exchange cobra comisión de retiro, súmala para que llegue el monto completo.
      @endif
      Pasado el plazo, el pedido se cancela y la cotización deja de valer.
    </div>
  @else
    <div class="text">
      Todavía no registramos el pago. Apenas se confirme, te enviamos otro correo y preparamos tu pedido.
      Si ya pagaste, puede demorar unos minutos en acreditarse.
    </div>
  @endif

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

  @if(!empty($pago))
    <a href="{{ $enlacePago }}" class="btn">Ver datos de pago y enviar comprobante</a>
  @else
    <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="btn">Ver mi pedido</a>
  @endif
@endsection

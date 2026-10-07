@extends('emails.layouts.base')

@section('emoji', '🎉')

@section('content')
  <div class="greeting">Hola{{ $user->first_name ? ' ' . $user->first_name : '' }},</div>
  <div class="title">¡Bienvenido a FatherMotoSport!</div>
  <div class="text">Gracias por crear tu cuenta. Como regalo de bienvenida, aquí tienes un cupón de <strong style="color:#fff">$5 de descuento</strong> para tu primera compra.</div>

  <div class="coupon-box">
    <div class="coupon-label">Tu cupón de bienvenida</div>
    <div class="coupon-code">{{ $coupon->code }}</div>
    <div class="coupon-value">$5.00 de descuento</div>
    @if($coupon->expires_at)
      <div class="countdown">
        <div class="countdown-label">Válido hasta</div>
        <div class="countdown-time">{{ $coupon->expires_at->format('d/m/Y H:i') }}</div>
      </div>
    @endif
  </div>

  <div class="text">Usá el código al finalizar tu compra. Recordá que el envío siempre es <strong style="color:#fff">gratis</strong> a toda América y Europa.</div>

  <a href="{{ config('app.frontend_url') }}/catalog" class="btn">Explorar productos</a>
@endsection

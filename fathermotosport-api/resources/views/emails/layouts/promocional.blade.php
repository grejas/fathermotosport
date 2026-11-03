@php
  // Plantilla de correos PROMOCIONALES (cumpleaños, avisos de descuento, campañas).
  // Los transaccionales usan emails.layouts.base, sin enlace de baja.
  // Variables: $idiomaCorreo (es, pt, en), $urlBaja (enlace firmado a la página /baja).
  // Opcional: $mostrarBaja (por defecto true). En false el pie no muestra el enlace de baja;
  // las cabeceras List-Unsubscribe de CorreoPromocional siguen igual.
  $idiomaCorreo = in_array($idiomaCorreo ?? null, ['es', 'pt', 'en'], true) ? $idiomaCorreo : 'es';
  $mostrarBaja = $mostrarBaja ?? true;
  $whatsappPie = \App\Support\PieDeCorreo::whatsappUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ $idiomaCorreo }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; padding:0; background:#f5f5f5; font-family:'Inter',Arial,sans-serif; }
  .wrapper { padding:32px 16px; background:#f5f5f5; }
  .card { max-width:520px; margin:0 auto; background:#0A0A0A; border-radius:16px; overflow:hidden; }
  .header { background:#E8001D; padding:24px 32px; }
  .logo { font-size:18px; font-weight:700; color:#fff; letter-spacing:0.06em; }
  .logo span { color:rgba(255,255,255,0.7); }
  .body { padding:32px; }
  .title { font-size:24px; font-weight:700; color:#fff; line-height:1.2; margin-bottom:16px; }
  .text { font-size:13px; color:rgba(255,255,255,0.7); line-height:1.7; margin-bottom:16px; }
  .btn { display:block; background:#E8001D; color:#fff !important; text-align:center; padding:14px 28px; border-radius:10px; font-size:13px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; text-decoration:none; margin:20px 0; }
  .footer { background:#050505; padding:20px 32px; border-top:1px solid rgba(255,255,255,0.06); }
  .footer-text { font-size:11px; color:rgba(255,255,255,0.3); text-align:center; line-height:1.7; }
  .footer-links { text-align:center; margin-top:10px; }
  .footer-link { font-size:11px; color:rgba(232,0,29,0.6); text-decoration:none; margin:0 10px; }
  .unsubscribe { font-size:10px; color:rgba(255,255,255,0.3); text-decoration:underline; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo"><span>Father</span>MotoSport</div>
    </div>
    <div class="body">
      @yield('content')
    </div>
    <div class="footer">
      <div class="footer-text">
        {{ \App\Support\PieDeCorreo::TIENDA }}<br>
        {{ __('marketing.footer_reason', [], $idiomaCorreo) }}
      </div>
      <div class="footer-links">
        <a href="{{ config('app.frontend_url') }}" class="footer-link">{{ __('marketing.visit_store', [], $idiomaCorreo) }}</a>
        @if ($whatsappPie)
        <a href="{{ $whatsappPie }}" class="footer-link">WhatsApp</a>
        @endif
      </div>
      @if ($mostrarBaja)
      <div class="footer-links">
        <a href="{{ $urlBaja }}" class="unsubscribe">{{ __('marketing.unsubscribe', [], $idiomaCorreo) }}</a>
      </div>
      @endif
    </div>
  </div>
</div>
</body>
</html>

@php
  // Pie del correo por idioma (ver App\Support\PieDeCorreo). Los correos que no pasan
  // $emailLocale salen en español, exactamente como antes.
  $idiomaCorreo = \App\Support\PieDeCorreo::idioma($emailLocale ?? null);
  $pie = \App\Support\PieDeCorreo::textos($idiomaCorreo);
@endphp
<!DOCTYPE html>
<html lang="{{ $idiomaCorreo }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; padding:0; background:#f5f5f5; font-family:'Inter',Arial,sans-serif; }
  .wrapper { padding:32px 16px; background:#f5f5f5; }
  .card { max-width:520px; margin:0 auto; background:#0A0A0A; border-radius:16px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
  .header { background:#E8001D; padding:24px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo { font-size:18px; font-weight:700; color:#fff; letter-spacing:0.06em; }
  .logo span { color:rgba(255,255,255,0.7); }
  .body { padding:32px; }
  .badge { display:inline-flex; align-items:center; gap:6px; background:rgba(0,200,151,0.12); border:1px solid rgba(0,200,151,0.3); border-radius:20px; padding:5px 12px; font-size:11px; color:#00C897; font-weight:500; margin-bottom:16px; }
  .badge-dot { width:6px; height:6px; border-radius:50%; background:#00C897; }
  .greeting { font-size:13px; color:rgba(255,255,255,0.5); margin-bottom:6px; }
  .title { font-size:24px; font-weight:700; color:#fff; line-height:1.2; margin-bottom:16px; }
  .text { font-size:13px; color:rgba(255,255,255,0.6); line-height:1.7; margin-bottom:16px; }
  .box { background:#111; border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:18px; margin:16px 0; }
  .row { display:flex; justify-content:space-between; font-size:12px; margin-bottom:8px; }
  .row:last-child { margin-bottom:0; }
  .row-k { color:rgba(255,255,255,0.4); }
  .row-v { color:#fff; font-weight:500; }
  .divider { height:1px; background:rgba(255,255,255,0.08); margin:16px 0; }
  .product-row { display:flex; gap:12px; align-items:center; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.06); }
  .product-row:last-child { border-bottom:none; }
  .product-icon { width:44px; height:44px; background:#1a1a1a; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .product-name { font-size:12px; font-weight:600; color:#fff; }
  .product-meta { font-size:11px; color:rgba(255,255,255,0.4); margin-top:2px; }
  .product-price { font-size:13px; font-weight:700; color:#fff; margin-left:auto; flex-shrink:0; }
  .total-row { display:flex; justify-content:space-between; padding-top:12px; margin-top:12px; border-top:1px solid rgba(255,255,255,0.1); }
  .total-k { font-size:13px; font-weight:600; color:#fff; }
  .total-v { font-size:20px; font-weight:700; color:#E8001D; }
  .btn { display:block; background:#E8001D; color:#fff !important; text-align:center; padding:14px 28px; border-radius:10px; font-size:13px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; text-decoration:none; margin:20px 0; }
  .coupon-box { background:linear-gradient(135deg,#1a0505,#2a0808); border:2px dashed rgba(232,0,29,0.4); border-radius:12px; padding:22px; text-align:center; margin:16px 0; }
  .coupon-label { font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(232,0,29,0.7); margin-bottom:10px; }
  .coupon-code { font-size:26px; font-weight:800; color:#fff; letter-spacing:0.2em; font-family:monospace; margin-bottom:8px; }
  .coupon-value { font-size:13px; color:rgba(255,255,255,0.5); }
  .countdown { background:#111; border-radius:8px; padding:10px; text-align:center; margin-top:12px; }
  .countdown-label { font-size:10px; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:4px; }
  .countdown-time { font-size:20px; font-weight:800; color:#E8001D; font-family:monospace; }
  .steps { display:flex; justify-content:space-between; margin-top:20px; position:relative; }
  .steps::before { content:''; position:absolute; top:11px; left:10%; right:10%; height:1px; background:rgba(255,255,255,0.1); }
  .step { text-align:center; flex:1; position:relative; }
  .step-dot { width:22px; height:22px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; }
  .step-done { background:#E8001D; color:#fff; }
  .step-current { background:#E8001D; color:#fff; box-shadow:0 0 0 4px rgba(232,0,29,0.2); }
  .step-pending { background:#1a1a1a; color:rgba(255,255,255,0.3); border:1px solid rgba(255,255,255,0.1); }
  .step-label { font-size:9px; color:rgba(255,255,255,0.35); text-transform:uppercase; letter-spacing:0.08em; }
  .creds-box { background:#111; border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:16px; margin:14px 0; }
  .cred-row { display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.06); }
  .cred-row:last-child { border-bottom:none; }
  .cred-k { font-size:10px; color:rgba(255,255,255,0.35); text-transform:uppercase; letter-spacing:0.08em; width:80px; flex-shrink:0; }
  .cred-v { font-size:12px; color:#fff; font-family:monospace; }
  .warning { background:rgba(201,168,76,0.08); border:1px solid rgba(201,168,76,0.25); border-radius:8px; padding:12px 14px; font-size:12px; color:rgba(201,168,76,0.9); margin:14px 0; }
  .footer { background:#050505; padding:20px 32px; border-top:1px solid rgba(255,255,255,0.06); }
  .footer-text { font-size:11px; color:rgba(255,255,255,0.2); text-align:center; line-height:1.7; }
  .footer-links { text-align:center; margin-top:10px; }
  .footer-link { font-size:11px; color:rgba(232,0,29,0.5); text-decoration:none; margin:0 10px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo"><span>Father</span>MotoSport</div>
      <div style="font-size:28px">@yield('emoji')</div>
    </div>
    <div class="body">
      @yield('content')
    </div>
    <div class="footer">
      <div class="footer-text">
        {{ \App\Support\PieDeCorreo::TIENDA }}<br>
        {{ $pie['envio'] }}
      </div>
      <div class="footer-links">
        <a href="{{ config('app.frontend_url') }}" class="footer-link">{{ $pie['tienda'] }}</a>
        <a href="https://wa.me/59168736384" class="footer-link">WhatsApp</a>
        <a href="{{ config('app.frontend_url') }}/unsubscribe" class="footer-link">{{ $pie['baja'] }}</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>

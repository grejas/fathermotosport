@php
  $t = [
    'es' => ['Listo, te diste de baja', 'Ya no te enviaremos correos de promociones del popup. Tu cupón sigue siendo válido hasta su vencimiento.', 'Volver a la tienda'],
    'en' => ['Done, you are unsubscribed', 'We will no longer send you promotional emails from the popup. Your coupon stays valid until it expires.', 'Back to the store'],
    'pt' => ['Pronto, você cancelou a inscrição', 'Não enviaremos mais e-mails promocionais do popup. Seu cupom continua válido até o vencimento.', 'Voltar à loja'],
  ][$idioma] ?? null;
  $t ??= ['Listo, te diste de baja', 'Ya no te enviaremos correos de promociones del popup.', 'Volver a la tienda'];
@endphp
<!DOCTYPE html>
<html lang="{{ $idioma }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>FatherMotoSport</title></head>
<body style="margin:0;background:#0A0A0A;font-family:Arial,sans-serif;color:#fff;display:flex;min-height:100vh;align-items:center;justify-content:center;">
  <div style="max-width:420px;padding:32px;text-align:center;">
    <div style="font-size:18px;font-weight:700;letter-spacing:0.06em;margin-bottom:24px;"><span style="color:#E8001D;">Father</span>MotoSport</div>
    <h1 style="font-size:22px;margin:0 0 12px;">{{ $t[0] }}</h1>
    <p style="font-size:14px;color:rgba(255,255,255,0.7);line-height:1.6;">{{ $t[1] }}</p>
    <a href="{{ config('app.frontend_url') }}" style="display:inline-block;margin-top:20px;background:#E8001D;color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:700;font-size:13px;">{{ $t[2] }}</a>
  </div>
</body>
</html>

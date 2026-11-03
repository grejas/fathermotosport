<?php

/*
|--------------------------------------------------------------------------
| Correo promocional (remarketing)
|--------------------------------------------------------------------------
|
| Fase 1: destinatarios válidos, baja y cupo de envío. Las fases 2 (cumpleaños),
| 3 (aviso de descuento de categoría) y 4 (campañas, cola y webhook de Resend) usan
| estos mismos valores.
|
| El plan gratuito de Resend permite 3.000 correos al mes y 100 al día: el tope
| diario por defecto es ese, y el límite por minuto deja margen al límite de la API.
*/

return [

    // Zona horaria de los envíos programados (cumpleaños a las 10:00, campañas).
    'timezone' => env('MARKETING_TIMEZONE', 'America/La_Paz'),

    'birthday_send_time' => '10:00',

    // Cupo de correos promocionales. Los transaccionales (pedidos, pagos) no cuentan.
    'limits' => [
        'per_minute' => (int) env('MARKETING_PER_MINUTE', 60),
        'per_day' => (int) env('MARKETING_PER_DAY', 100),
    ],

    // Dominios de correo desechable conocidos: se rechazan en el registro y al verificar.
    // Lista corta a propósito; se mantiene a mano.
    'disposable_domains' => [
        '10minutemail.com',
        'dispostable.com',
        'emailondeck.com',
        'fakeinbox.com',
        'getnada.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'maildrop.cc',
        'mailinator.com',
        'mailnesia.com',
        'mintemail.com',
        'mohmal.com',
        'sharklasers.com',
        'spamgourmet.com',
        'temp-mail.org',
        'tempmail.com',
        'tempmail.dev',
        'throwawaymail.com',
        'trashmail.com',
        'yopmail.com',
    ],

];

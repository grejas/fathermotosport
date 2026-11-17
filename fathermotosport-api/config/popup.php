<?php

/*
|--------------------------------------------------------------------------
| Popup de captación (cupón a cambio del email)
|--------------------------------------------------------------------------
|
| Cada contacto nuevo recibe UN cupón de porcentaje, de un solo uso, sin dueño
| (sirve también en compras sin cuenta). El porcentaje sale al azar según los pesos:
| con los valores de abajo, de cada 100 contactos ~50 reciben 10 %, ~30 el 15 %,
| ~15 el 20 % y ~5 el 25 %. Cambiar los pesos cambia el costo medio del descuento.
|
| POPUP_WEIGHTS="10:50,15:30,20:15,25:5" en el .env del servidor permite ajustarlo
| sin tocar código (luego: php artisan config:clear).
|
*/

$pesos = [];
foreach (explode(',', (string) env('POPUP_WEIGHTS', '10:50,15:30,20:15,25:5')) as $par) {
    [$porcentaje, $peso] = array_pad(explode(':', trim($par)), 2, 0);
    if ((int) $porcentaje > 0 && (int) $peso > 0) {
        $pesos[(int) $porcentaje] = (int) $peso;
    }
}

return [
    // porcentaje => peso relativo
    'weights' => $pesos ?: [10 => 50, 15 => 30, 20 => 15, 25 => 5],

    // Días de validez del cupón desde que se entrega.
    'valid_days' => (int) env('POPUP_VALID_DAYS', 7),

    // Compra mínima para usar el cupón (USD). 0 = sin mínimo.
    'minimum_amount' => (float) env('POPUP_MINIMUM_AMOUNT', 0),

    // Máximo de contactos nuevos por IP y por día (freno contra quien crea emails falsos).
    'max_per_ip_per_day' => (int) env('POPUP_MAX_PER_IP_PER_DAY', 3),
];

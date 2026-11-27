#!/bin/bash
# Mercado Pago México: cobro en MXN al tipo de cambio del día. Sin Access Token en el .env
# la ruta responde 503 y la web no muestra el botón, así que es seguro desplegarlo antes.
# Solo pisa si el servidor tiene la versión de main anterior a este cambio.
set -u
COMMIT="49cea740f1715532b407aadf054efd00f40b2a7b"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
declare -A BASE=(
  ["app/Http/Controllers/Api/PaymentController.php"]="29f7b831b68d2a5f7696384fade53d5c"
  ["app/Models/StoreConfig.php"]="0f012cec318aa9de48565986c340785e"
  ["app/Services/Payments/ManualPaymentService.php"]="3f533e42311988936f03fbe5cb7b3f22"
  ["app/Services/Payments/MercadoPagoService.php"]="0dd60cb6468e57f98c058b066ab98436"
  ["config/services.php"]="dddad7b5302a47a29e1dbf68ccfb3b8a"
  ["routes/api.php"]="d1024acc0aea2249f032d25232426035"
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con lo desplegado. No se tocó nada."; exit 1; }
done
for f in "${!BASE[@]}"; do
  cp "$f" "$f.bak-mercadopago"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; cp "$f.bak-mercadopago" "$f"; exit 1; }
done
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
echo "Token cargado: $($PHP artisan tinker --execute='echo App\Services\Payments\MercadoPagoService::configurado() ? "SI" : "NO";')"
echo "Tipo de cambio hoy (USD por 1 MXN): $($PHP artisan tinker --execute='echo app(App\Services\Payments\ManualPaymentService::class)->usdPorPesoMexicano() ?? "SIN DATO";')"
echo "Ruta create: $(curl -s -o /dev/null -w '%{http_code}' -X POST https://api.fathermotosport.com/api/v1/payments/mercadopago/create)"

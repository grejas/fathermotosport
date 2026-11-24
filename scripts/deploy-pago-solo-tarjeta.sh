#!/bin/bash
# Pagos: configuración "solo tarjeta" (Stripe ya no acepta payment_method_types) y
# cobro en un toque arreglado. Solo pisa si el servidor tiene la versión desplegada antes.
set -u
COMMIT="f9ab52a28585a06be13555897406fe1c344f4c8c"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
declare -A BASE=(
  ["app/Http/Controllers/Api/PaymentController.php"]="f6774a00dd1cc19db9ae403ba083dece"
  ["app/Services/Payments/StripeService.php"]="02240905d3b57452c202536a7b927257"
  ["routes/api.php"]="96a39b7488e7e9b979fca421d5858010"
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con lo desplegado. No se tocó nada."; exit 1; }
done
for f in "${!BASE[@]}"; do
  cp "$f" "$f.bak-solotarjeta"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; cp "$f.bak-solotarjeta" "$f"; exit 1; }
done
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Config solo tarjeta: $($PHP artisan tinker --execute='echo app(App\Services\Payments\StripeService::class)->configuracionSoloTarjeta() ?? "NINGUNA";')"

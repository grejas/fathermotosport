#!/bin/bash
# PayPal como pago manual (paypal_manual). Solo pisa si el servidor tiene la versión de main anterior.
set -u
COMMIT="532753a505c7a30383e945a4a5d7309d797268d5"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
MIG="database/migrations/2026_11_28_150001_add_paypal_manual.php"
cd "$APP" || exit 1
declare -A BASE=(
  ["app/Filament/Pages/StoreSettingsPage.php"]="21e86883ae26cbace5a4909e136cd12a"
  ["app/Http/Controllers/Api/OrderController.php"]="b5e400e98c1b06b2d282bfb8c4ad4757"
  ["app/Http/Requests/Order/StoreOrderRequest.php"]="f78b40cad897ca6679a50100afbfdfee"
  ["app/Models/Order.php"]="8714a04de31755ec2242f07957a2691f"
  ["app/Models/StoreConfig.php"]="2a7706dac7ccce666811ba0238e60d1e"
  ["app/Services/Payments/ManualPaymentService.php"]="ea69a104a1b931b1c7dec0a93ead3e2c"
  ["resources/views/emails/order-received.blade.php"]="2963a337a031c0ea935a463ff305b62b"
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con lo desplegado. No se tocó nada."; exit 1; }
done
[ -e "$MIG" ] && { echo "ABORTO: la migración ya existe"; exit 1; }
curl -fsSL "$RAW/$MIG" -o "$MIG" || { echo "FALLO descarga migración"; exit 1; }
$PHP artisan migrate --path="$MIG" --force || { echo "FALLO migración"; rm -f "$MIG"; exit 1; }
for f in "${!BASE[@]}"; do
  cp "$f" "$f.bak-ppmanual"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  case "$f" in *.php) $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; cp "$f.bak-ppmanual" "$f"; exit 1; };; esac
done
$PHP artisan optimize:clear >/dev/null
$PHP artisan config:cache >/dev/null
$PHP artisan route:cache >/dev/null
$PHP artisan view:clear >/dev/null
echo "Settings: $(curl -s https://api.fathermotosport.com/api/v1/store/settings | grep -o '"paypal_manual":[a-z]*\|"mercadopago_mx":[a-z]*' | tr '\n' ' ')"

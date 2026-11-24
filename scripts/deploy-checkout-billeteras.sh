#!/bin/bash
# Checkout: tarjeta y billetera separadas (mode=card|wallet en /payments/stripe/intent).
# Solo pisa los archivos si el servidor tiene exactamente la versión desplegada del #48.
set -u
COMMIT="3a47827da8265aa8804d0ebe4caeadd545ce13d5"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
declare -A BASE=(
  ["app/Http/Controllers/Api/PaymentController.php"]="048df071f9e19015ac90bae5cab6e94a"
  ["app/Services/Payments/StripeService.php"]="aa7c35ee5e0fcc8e8d25d04f39be08b4"
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con lo desplegado (#48). No se tocó nada."; exit 1; }
done
for f in "${!BASE[@]}"; do
  cp "$f" "$f.bak-billeteras"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; cp "$f.bak-billeteras" "$f"; exit 1; }
done
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Desplegado: tarjeta y billetera separadas."

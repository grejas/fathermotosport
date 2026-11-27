#!/bin/bash
# Checkout (27-nov-2026): el panel de pedidos muestra la dirección completa en el orden del
# proveedor (con estado y código postal). Verifica md5 contra main antes de pisar el archivo.
set -u
COMMIT="9889edec1244e7631c0caec68bc69fe5a4d98205"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
F="app/Filament/Resources/OrderResource.php"
actual=$(md5sum "$F" | cut -d' ' -f1)
[ "$actual" = "bdeca786e216fa24e6f0f823e589ca38" ] || { echo "ABORTO: $F no coincide con main ($actual). No se tocó nada."; exit 1; }
cp "$F" "$F.bak-checkout-direccion"
curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP -l "$F" >/dev/null || { echo "ERROR de sintaxis; restaurando"; cp "$F.bak-checkout-direccion" "$F"; exit 1; }
$PHP artisan optimize:clear
$PHP artisan filament:cache-components 2>/dev/null || true
echo "OrderResource actualizado."

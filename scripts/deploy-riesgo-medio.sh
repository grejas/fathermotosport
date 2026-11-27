#!/bin/bash
# Riesgo medio de la auditoría legal (rama feat/riesgo-medio, pedido de Gerson, 27-nov-2026):
# el pedido guarda cuándo y qué versión de los Términos aceptó el cliente.
# Antes de pisar cada archivo verifica que el del servidor sea idéntico al de main;
# si alguno difiere, no toca nada. Guarda copias .bak-riesgo-medio.
set -u
COMMIT="fb8aea775b260e1b54fe5fd0a328c7c5257251e2"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

declare -A BASE=(
  ["app/Http/Requests/Order/StoreOrderRequest.php"]="2846ca3a10dcb97fca9b22c9262027d7"
  ["app/Models/Order.php"]="d254fa761bc0c6a53ed20e6a8c5997ac"
  ["app/Services/OrderService.php"]="976b7b84dd88d8718172892515f4a95b"
)
NUEVOS=(
  database/migrations/2026_11_27_100005_add_terms_acceptance_to_orders.php
)

for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" 2>/dev/null | cut -d' ' -f1)
  if [ "$actual" != "${BASE[$f]}" ]; then
    echo "ABORTO: $f en el servidor no coincide con main (servidor $actual, main ${BASE[$f]}). No se tocó nada."
    exit 1
  fi
done
echo "Servidor = main. Desplegando…"

for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  [ -f "$f" ] && cp "$f" "$f.bak-riesgo-medio"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
done
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; }
done

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan view:clear
$PHP artisan filament:cache-components 2>/dev/null || true

$PHP artisan tinker --execute='
echo "Columnas de aceptación: ".(Schema::hasColumn("orders","terms_accepted_at") && Schema::hasColumn("orders","terms_version") ? "OK" : "FALTAN").PHP_EOL;
'

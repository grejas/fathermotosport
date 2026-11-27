#!/bin/bash
# Checkout + pendientes legales (27-nov-2026):
# - Panel de pedidos: dirección completa en el orden del proveedor (estado, CP, referencia).
# - Correos promocionales: operador y dirección postal (CAN-SPAM).
# Verifica md5 de cada archivo contra main antes de pisar nada; si uno no coincide, no toca ninguno.
set -u
COMMIT="d957732768104dd8bb4c3fee64a7239dc7ea34c1"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

ARCHIVOS=(
  "app/Filament/Resources/OrderResource.php bdeca786e216fa24e6f0f823e589ca38"
  "app/Support/PieDeCorreo.php 39d65e3118385c999b7e721a4633256a"
  "resources/views/emails/layouts/promocional.blade.php 7d041601b6058575a4c9f06eaaa7e193"
  "resources/views/emails/aviso-descuento.blade.php 0d5507119c0dd53f3e310d88d833bd64"
  "resources/views/emails/campana.blade.php 9f2fb9a955c9238735c5493cad217650"
)

for linea in "${ARCHIVOS[@]}"; do
  set -- $linea
  actual=$(md5sum "$1" | cut -d' ' -f1)
  [ "$actual" = "$2" ] || { echo "ABORTO: $1 no coincide con main ($actual). No se tocó nada."; exit 1; }
done

for linea in "${ARCHIVOS[@]}"; do
  set -- $linea
  curl -fsSL "$RAW/$1" -o "$1.nuevo" || { echo "FALLO descarga $1. No se tocó nada."; for l in "${ARCHIVOS[@]}"; do rm -f "${l%% *}.nuevo"; done; exit 1; }
done

for linea in "${ARCHIVOS[@]}"; do
  set -- $linea
  case "$1" in *.php) [[ "$1" == *.blade.php ]] || $PHP -l "$1.nuevo" >/dev/null || { echo "ERROR de sintaxis en $1. No se tocó nada."; exit 1; } ;; esac
done

for linea in "${ARCHIVOS[@]}"; do
  set -- $linea
  cp "$1" "$1.bak-checkout-legal"
  mv "$1.nuevo" "$1"
  echo "OK $1"
done

$PHP artisan optimize:clear
$PHP artisan filament:cache-components 2>/dev/null || true
$PHP artisan view:cache >/dev/null && echo "Vistas compiladas."
echo "Listo."

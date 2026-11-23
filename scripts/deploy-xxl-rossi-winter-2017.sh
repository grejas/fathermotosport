#!/bin/bash
# Agrega la talla XXL (5 u.) al Casco AGV Pista GP R Rossi Winter Test 2017 Tavullia.
set -u
COMMIT="1d3cfd7832bb8f33da0dc2ee0714d6fa9717f07d"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
F=database/migrations/2026_11_23_100006_xxl_casco_agv_pista_gp_r_rossi_winter_test_2017_tavullia.php
curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Stock por talla (XXL debe ser 5):"
$PHP artisan tinker --execute="echo App\\Models\\Product::where('slug','casco-agv-pista-gp-r-rossi-winter-test-2017-tavullia')->first()?->variants()->pluck('stock','size')->toJson();"

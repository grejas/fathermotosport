#!/bin/bash
# Agota el Casco AGV Pista GP R Rossi Mugello 2019 (stock 0 en todas las tallas).
set -u
COMMIT="02d78929dd33b63d7e886e1bbc9fabba8f428472"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
F=database/migrations/2026_11_23_100004_agotar_casco_agv_pista_gp_r_rossi_mugello_2019.php
curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Stock por talla (debe ser todo 0):"
$PHP artisan tinker --execute="echo App\\Models\\Product::where('slug','casco-agv-pista-gp-r-rossi-mugello-2019')->first()?->variants()->pluck('stock','size')->toJson();"

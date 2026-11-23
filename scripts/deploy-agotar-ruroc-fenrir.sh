#!/bin/bash
# Agota el Casco Ruroc Atlas 4.0 Fenrir (stock 0 en todas las tallas).
set -u
COMMIT="3d3e2d5295033032d255ac3f4afd36cd7764106e"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
F=database/migrations/2026_11_23_100005_agotar_casco_ruroc_atlas_40_fenrir.php
curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Stock por talla (debe ser todo 0):"
$PHP artisan tinker --execute="echo App\\Models\\Product::where('slug','casco-ruroc-atlas-40-fenrir')->first()?->variants()->pluck('stock','size')->toJson();"

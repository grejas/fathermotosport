#!/bin/bash
# Video de presentación (primero en la galería) de la Chaqueta Alpinestars T-Supertech Air Negro.
set -u
COMMIT="d4d7ca8448feafc01f6c44fed6b63999e2b80bf0"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
mkdir -p database/seed-assets/videos
f() { curl -fsSL "$RAW/$1" -o "$1.tmp" && mv "$1.tmp" "$1" || { echo "FALLO descarga $1"; exit 1; }; }
f database/seed-assets/videos/supertech-air-negro.mp4
f database/seed-assets/videos/supertech-air-negro-poster.jpg
f database/migrations/2026_11_23_100008_video_chaqueta_alpinestars_t_supertech_air_negro.php
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Galería (orden: tipo):"
$PHP artisan tinker --execute="echo App\\Models\\Product::where('slug','chaqueta-alpinestars-t-supertech-air-negro')->first()?->images()->orderBy('sort_order')->get(['sort_order','media_type','url'])->toJson();"

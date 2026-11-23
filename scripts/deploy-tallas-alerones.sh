#!/bin/bash
# Alerones: tallas M, L y XXL (cada una con el stock que tenía) + selector de talla en el panel.
set -u
COMMIT="10961cb475bf59e5dd065f94f908f3616d18e62f"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
cp app/Filament/Resources/ProductResource.php "app/Filament/Resources/ProductResource.php.bak-$(date +%Y%m%d%H%M)"
f() { curl -fsSL "$RAW/$1" -o "$1.tmp" && mv "$1.tmp" "$1" || { echo "FALLO descarga $1"; exit 1; }; }
f app/Filament/Resources/ProductResource.php
f database/migrations/2026_11_23_100007_tallas_m_l_xxl_en_alerones.php
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
echo "Alerones (talla: stock):"
$PHP artisan tinker --execute="foreach (App\\Models\\Product::where('slug','like','aleron-de-casco-%')->get() as \$p) { echo \$p->slug.' '.\$p->variants()->pluck('stock','size')->toJson().PHP_EOL; }"

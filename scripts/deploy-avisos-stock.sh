#!/bin/bash
# Despliega en Hostinger (API) los «Avisos de stock» y agota el
# Casco AGV Pista GP R World Title 2002.
# Uso (dentro de SSH):  bash deploy-avisos-stock.sh
set -u
COMMIT="1bd0092508949c0bff4f2122f8d5b608c4f8c574"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

# Respaldo de lo único que se modifica (lo demás son archivos nuevos).
cp routes/api.php "routes/api.php.bak-$(date +%Y%m%d%H%M)"

mkdir -p app/Filament/Resources/StockAlertResource/Pages
f() { curl -fsSL "$RAW/$1" -o "$1.tmp" && mv "$1.tmp" "$1" || { echo "FALLO descarga $1"; exit 1; }; }
f app/Models/StockAlert.php
f app/Http/Controllers/Api/StockAlertController.php
f app/Filament/Resources/StockAlertResource.php
f app/Filament/Resources/StockAlertResource/Pages/ListStockAlerts.php
f database/migrations/2026_11_23_100001_create_stock_alerts_table.php
f database/migrations/2026_11_23_100002_agotar_casco_agv_pista_gp_r_world_title_2002.php
f routes/api.php
echo "Archivos descargados ($COMMIT)"

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan route:list --path=stock-alert
echo "Stock del casco (debe ser 0):"
$PHP artisan tinker --execute="echo App\Models\Product::where('slug','casco-agv-pista-gp-r-world-title-2002')->first()?->variants()->sum('stock');"

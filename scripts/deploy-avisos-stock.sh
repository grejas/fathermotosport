#!/bin/bash
# Despliega en Hostinger (API) los «Avisos de stock» y agota el
# Casco AGV Pista GP R World Title 2002; deja el 2003 con 10 unidades por talla.
# Uso (dentro de SSH):  bash deploy-avisos-stock.sh
set -u
COMMIT="0c3696d49c011bce9208eb59a660ab27143591b0"
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
f database/migrations/2026_11_23_100003_stock_10_casco_agv_pista_gp_r_world_title_2003.php
f routes/api.php
echo "Archivos descargados ($COMMIT)"

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan route:list --path=stock-alert
echo "Stock por talla (2002 debe ser todo 0 · 2003 todo 10):"
$PHP artisan tinker --execute="foreach (['2002','2003'] as \$a) { \$p = App\Models\Product::where('slug','casco-agv-pista-gp-r-world-title-'.\$a)->first(); echo \$a.': '.\$p?->variants()->pluck('stock','size')->toJson().PHP_EOL; }"

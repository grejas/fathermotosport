#!/bin/bash
# Despliega products:import-heubow en Hostinger y lo ejecuta.
# Uso: bash deploy-heubow.sh [dry|apply]   (por defecto: dry)
set -u
MODO="${1:-dry}"
COMMIT="d092397f90f16a9080387aaef13012c9cad123d1"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
mkdir -p database/seed-assets/heubow
f() { curl -fsSL "$RAW/$1" -o "$1.tmp" && mv "$1.tmp" "$1" || { echo "FALLO descarga $1"; exit 1; }; }
f app/Console/Commands/ImportarHoodiesHeubow.php
for n in 11-1 11-2 12-1 12-2 13-1 13-2 14-1 15-1 16-1 17-1 18-1 19-1 detalle-cierre-cruz existente-02 existente-03 existente-04 existente-05 existente-06 existente-07 existente-08 existente-09 existente-10; do
  f "database/seed-assets/heubow/$n.jpg"
done
echo "Archivos descargados ($COMMIT)"
$PHP artisan optimize:clear
if [ "$MODO" = "apply" ]; then
  $PHP artisan products:import-heubow --apply
else
  $PHP artisan products:import-heubow
fi

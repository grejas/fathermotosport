#!/bin/bash
# Plazo 7-9 días hábiles a Europa, EE.UU. y México (pedido de Gerson, 26-nov-2026).
# Solo agrega una migración que actualiza estimated_days_min/max en shipping_options
# (no pisa archivos existentes). Muestra la tabla resultante para verificar.
set -u
COMMIT="eadf8d56870b7342296e69c3aa5a40083eeaeb85"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

F="database/migrations/2026_11_26_200003_days_7_9_europe_us_mx.php"

echo "Plazos ANTES:"
$PHP artisan tinker --execute='
foreach (DB::table("shipping_options")->orderBy("country_code")->orderBy("method_name")->get() as $o) {
  echo $o->country_code." | ".$o->method_name." | ".($o->is_active ? "activa" : "inactiva")." | ".var_export($o->estimated_days_min, true)."-".var_export($o->estimated_days_max, true).PHP_EOL;
}'

curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP -l "$F" >/dev/null || { echo "ERROR de sintaxis en $F"; exit 1; }

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache

echo "Plazos DESPUÉS (días hábiles totales):"
$PHP artisan tinker --execute='
$sin = 0;
foreach (DB::table("shipping_options")->orderBy("country_code")->orderBy("method_name")->get() as $o) {
  if ($o->is_active && ($o->estimated_days_min === null || $o->estimated_days_max === null)) $sin++;
  echo $o->country_code." | ".$o->method_name." | ".($o->is_active ? "activa" : "inactiva")." | ".$o->estimated_days_min."-".$o->estimated_days_max.PHP_EOL;
}
echo "Opciones activas sin plazo: ".$sin.PHP_EOL;'

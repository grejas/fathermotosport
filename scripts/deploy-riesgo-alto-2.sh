#!/bin/bash
# Segunda parte del riesgo alto (27-nov-2026): la primera corrida falló en la migración de
# withdrawal_requests (order_id debía ser UUID). Sube la migración corregida y corre las
# pendientes (100002, 100003, 100004). El resto ya quedó desplegado en la primera corrida:
# se verifica que routes/api.php del servidor sea la versión nueva antes de seguir.
set -u
COMMIT="1688db047ca78ab54e21ac7a075892329d0f70d4"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

actual=$(md5sum routes/api.php | cut -d' ' -f1)
[ "$actual" = "d1024acc0aea2249f032d25232426035" ] || { echo "ABORTO: routes/api.php no es la versión de la primera corrida ($actual). No se tocó nada."; exit 1; }

F="database/migrations/2026_11_27_100002_create_withdrawal_requests_table.php"
cp "$F" "$F.bak-intento1" 2>/dev/null
curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
$PHP -l "$F" >/dev/null || { echo "ERROR de sintaxis en $F"; exit 1; }

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan view:clear
$PHP artisan filament:cache-components 2>/dev/null || true

$PHP artisan tinker --execute='
echo "Migraciones pendientes: ".collect(DB::table("migrations")->pluck("migration"))->intersect(["2026_11_27_100002_create_withdrawal_requests_table","2026_11_27_100003_clear_wrong_certifications","2026_11_27_100004_remove_unconfirmed_dot"])->count()."/3 aplicadas".PHP_EOL;
echo "FK order_id: ".(collect(Schema::getForeignKeys("withdrawal_requests"))->isNotEmpty() ? "OK" : "FALTA").PHP_EOL;
echo "Productos con DOT: ".App\Models\Product::where("certification", "like", "%DOT%")->count().PHP_EOL;
echo "Productos no-casco con norma de casco: ".App\Models\Product::whereNotNull("certification")->whereHas("category", fn($q) => $q->where("slug", "!=", "cascos"))->where(fn($q) => $q->where("certification", "like", "%ECE%")->orWhere("certification", "like", "%DOT%"))->count().PHP_EOL;
'

#!/bin/bash
# Recarga la configuración tras cargar las credenciales de Mercado Pago en el .env.
# No imprime ningún secreto: solo si están cargados y su prefijo.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
echo "Lineas en .env: token=$(grep -c '^MERCADOPAGO_ACCESS_TOKEN=.\+' .env) webhook=$(grep -c '^MERCADOPAGO_WEBHOOK_SECRET=.\+' .env)"
$PHP artisan optimize:clear >/dev/null
$PHP artisan config:cache >/dev/null
$PHP artisan route:cache >/dev/null
$PHP artisan cache:clear >/dev/null
echo "Token: $($PHP artisan tinker --execute='$t=(string)config("services.mercadopago.access_token"); echo $t===""?"VACIO":(str_starts_with($t,"APP_USR-")?"OK produccion (APP_USR-)":(str_starts_with($t,"TEST-")?"PRUEBA (TEST-)":"formato raro"));')"
echo "Webhook secret: $($PHP artisan tinker --execute='echo filled(config("services.mercadopago.webhook_secret"))?"OK":"VACIO";')"
echo "Cuenta MP: $($PHP artisan tinker --execute='$r=Illuminate\Support\Facades\Http::withToken((string)config("services.mercadopago.access_token"))->get("https://api.mercadopago.com/users/me"); echo $r->status()." site=".($r->json("site_id")??"?");')"
echo "Settings: $(curl -s https://api.fathermotosport.com/api/v1/store/settings | grep -o '"gateways":{[^}]*}')"

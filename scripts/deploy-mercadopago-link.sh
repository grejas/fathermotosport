#!/bin/bash
# Mercado Pago México por link de cobro (Bolimex): método manual mercadopago_mx.
# Solo pisa si el servidor tiene la versión de main anterior a este cambio.
set -u
COMMIT="845bbf8417fa8f9a7ec62b7fdc4444d79132af15"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
MIG="database/migrations/2026_11_27_200001_add_mercadopago_link.php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
declare -A BASE=(
  ["app/Filament/Pages/StoreSettingsPage.php"]="48002f94142c6062b37e56f20055d84e"
  ["app/Http/Controllers/Api/OrderController.php"]="2cdbb3826fc58e6364c1db0af9c1db17"
  ["app/Http/Requests/Order/StoreOrderRequest.php"]="dc1e7a0932826c78bf36b72b6645a28d"
  ["app/Models/Order.php"]="bb4aef6a5e76582f6e0fd4f5764a9afb"
  ["app/Models/StoreConfig.php"]="2663ba6529240bf853fb90fc45297428"
  ["app/Services/Payments/ManualPaymentService.php"]="032c92c2498a14071a96a73275557c44"
  ["resources/views/emails/order-received.blade.php"]="766bf9edc996ec438d4df22dad364fd5"
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con lo desplegado ($actual). No se tocó nada."; exit 1; }
done
[ -e "$MIG" ] && { echo "ABORTO: la migración ya existe"; exit 1; }
curl -fsSL "$RAW/$MIG" -o "$MIG" || { echo "FALLO descarga migración"; exit 1; }
$PHP artisan migrate --path="$MIG" --force || { echo "FALLO migración"; rm -f "$MIG"; exit 1; }
for f in "${!BASE[@]}"; do
  cp "$f" "$f.bak-mplink"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  case "$f" in *.php) $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; cp "$f.bak-mplink" "$f"; exit 1; };; esac
done
$PHP artisan tinker --execute='App\Models\StoreConfig::current()->update(["mp_link_url" => "https://link.mercadopago.com.mx/bolimex", "mp_link_name" => "Bolimex Group", "mp_link_fee_percent" => 7]); echo "config ok";'
$PHP artisan optimize:clear >/dev/null
$PHP artisan config:cache >/dev/null
$PHP artisan route:cache >/dev/null
$PHP artisan view:clear >/dev/null
echo "Cotizacion: $($PHP artisan tinker --execute='$c=app(App\Services\Payments\ManualPaymentService::class)->cotizar("mercadopago_mx"); echo "430 USD = ".App\Services\Payments\ManualPaymentService::montoAPagar(430,$c)." MXN (con FEE)";')"
echo "Settings: $(curl -s https://api.fathermotosport.com/api/v1/store/settings | grep -o '"mercadopago_mx":[a-z]*')"

#!/bin/bash
# Color de visor en el pedido (rama feat/visor-en-pedido, pedido de Gerson, 26-nov-2026).
# Antes de pisar cada archivo verifica que el del servidor sea idéntico al de main
# (7b15c4f): si alguno difiere, no toca nada. Guarda copias .bak-visor para volver atrás.
set -u
COMMIT="7166c57b002929df2cd0dddae9ebd45b00a784ab"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

# md5 de main (7b15c4f) de cada archivo que se reemplaza
declare -A BASE=(
  ["app/Filament/Resources/OrderResource.php"]="07570c59b3e0381b10dd056f0c042c52"
  ["app/Http/Controllers/Api/ProductController.php"]="fc52853e88face49129054bead567d45"
  ["app/Http/Requests/Order/StoreExpressOrderRequest.php"]="607b87a4406f6766293d70bc4ab9665c"
  ["app/Http/Requests/Order/StoreOrderRequest.php"]="c7db66289d56a2e1c5a28992dc62d5bf"
  ["app/Http/Resources/OrderItemResource.php"]="7e5a9277b5041e477c5159a18214ca85"
  ["app/Models/OrderItem.php"]="b5f1daf32ca25c19611babb7679ded5c"
  ["app/Services/OrderService.php"]="694a57bbb2d3ce1d0482eff6ee6625b0"
  ["lang/en/payment_recovery.php"]="a0f1fdc9d132855a6c1b2f60db53434c"
  ["lang/es/payment_recovery.php"]="95e37c8bcc12940117e9a10c43da4fe3"
  ["lang/pt/payment_recovery.php"]="d603ad052cb2fd43288d5c605b59cc42"
  ["resources/views/emails/order-confirmed.blade.php"]="8526b351703bbb8b66ccb3f604924e0c"
  ["resources/views/emails/order-received.blade.php"]="0a8b38001e3c742113c40eea9cd52511"
  ["resources/views/emails/payment-recovery.blade.php"]="21d0195fbe36cf606bb4f16451e015e6"
)
NUEVOS=(
  database/migrations/2026_11_26_100001_add_visor_color_to_order_items_table.php
)

for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" 2>/dev/null | cut -d' ' -f1)
  if [ "$actual" != "${BASE[$f]}" ]; then
    echo "ABORTO: $f en el servidor no coincide con main (servidor $actual, main ${BASE[$f]}). No se tocó nada."
    exit 1
  fi
done
echo "Servidor = main en los ${#BASE[@]} archivos. Desplegando…"

for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  [ -f "$f" ] && cp "$f" "$f.bak-visor"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
done
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  case "$f" in *.blade.php) ;; *) $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; } ;; esac
done

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan view:clear
$PHP artisan filament:cache-components 2>/dev/null || true

# Verificación y catálogo de visores actual (solo lectura).
$PHP artisan tinker --execute='
echo "Columna visor_color: ".(Schema::hasColumn("order_items","visor_color") ? "OK" : "FALTA").PHP_EOL;
foreach (App\Models\VisorColor::orderBy("sort_order")->get() as $v) {
  echo ($v->is_active ? "ACTIVO   " : "inactivo ")."| ".$v->name." | ".$v->hex_color." | img:".($v->image_url ? "si" : "no")." | cascos con este visor: ".DB::table("product_visor_colors")->where("visor_color_id",$v->id)->count().PHP_EOL;
}
$cat = App\Models\Category::where("slug","cascos")->value("id");
echo "Cascos activos: ".App\Models\Product::where("category_id",$cat)->where("is_active",true)->count()." · con visores propios: ".App\Models\Product::where("category_id",$cat)->where("is_active",true)->has("visorColors")->count().PHP_EOL;
'

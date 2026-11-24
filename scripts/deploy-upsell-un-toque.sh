#!/bin/bash
# Upsell en un toque (PR #48) + productos de prueba de $1 (pedido de Gerson, 24-nov-2026).
# Antes de pisar cada archivo verifica que el del servidor sea idéntico al de main
# (961366e): si alguno difiere, no toca nada. Guarda copias .bak-upsell para volver atrás.
set -u
COMMIT="359b275ad6d2948baa7db76ca88a54529c046507"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

# md5 de main (961366e) de cada archivo que se reemplaza
declare -A BASE=(
  ["app/Filament/Resources/UpsellRuleResource.php"]="a10b0c099fd12bf58528539e3c111a9d"
  ["app/Http/Controllers/Api/PaymentController.php"]="7bba68bebc48469bd2cd7ae73366b9dd"
  ["app/Http/Controllers/Api/UpsellController.php"]="74513d2bfd2ff5e5afff5c5d2381b0c8"
  ["app/Http/Requests/Order/StoreUpsellRequest.php"]="741d3af361cb8718a74da3a0dda6d348"
  ["app/Models/UpsellRule.php"]="d2975df368bbd3980be4fda47e92b741"
  ["app/Services/Payments/StripeService.php"]="8334852d295e7fad8cd29863b485a89c"
  ["app/Services/UpsellService.php"]="ef501621ecd7b10122ed88a915a7025e"
  ["routes/api.php"]="e65ac3edca829dfff0b516060dcc9c3f"
)
NUEVOS=(
  database/migrations/2026_11_24_100001_add_last_chance_and_group_to_upsell_rules_table.php
  database/migrations/2026_11_24_100002_reglas_upsell_para_cascos.php
)

for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" 2>/dev/null | cut -d' ' -f1)
  if [ "$actual" != "${BASE[$f]}" ]; then
    echo "ABORTO: $f en el servidor no coincide con main (servidor $actual, main ${BASE[$f]}). No se tocó nada."
    exit 1
  fi
done
echo "Servidor = main en los 8 archivos. Desplegando…"

for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  [ -f "$f" ] && cp "$f" "$f.bak-upsell"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
done
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; }; done

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan filament:cache-components 2>/dev/null || true

# Productos de prueba de $1 y su regla (se apagan con apagar-prueba-upsell.sh).
$PHP artisan tinker --execute='
$cat = App\Models\Category::where("slug","repuestos")->value("id");
$brand = App\Models\Brand::value("id");
$img = App\Models\ProductImage::whereHas("product", fn($q) => $q->where("name","like","Visor Pista AGV%"))->value("url");
$mk = function ($nombre, $slug, $talla) use ($cat, $brand, $img) {
  $p = App\Models\Product::firstOrCreate(["slug"=>$slug], ["name"=>$nombre,"sku"=>strtoupper($slug),"price"=>1,"sale_price"=>null,"category_id"=>$cat,"brand_id"=>$brand,"is_active"=>true,"description"=>"Producto de prueba interno. No comprar.","weight"=>0.1]);
  $p->update(["is_active"=>true,"price"=>1,"sale_price"=>null]);
  if ($p->variants()->count() === 0) $p->variants()->create(["size"=>$talla,"sku"=>strtoupper($slug)."-V","stock"=>20,"is_active"=>true]);
  if ($img && $p->images()->count() === 0) $p->images()->create(["url"=>$img,"media_type"=>"image","sort_order"=>0,"is_primary"=>true]);
  return $p;
};
$casco = $mk("PRUEBA casco \$1", "prueba-casco-1", "M");
$acc = $mk("PRUEBA accesorio \$1", "prueba-accesorio-1", null);
App\Models\UpsellRule::updateOrCreate(["trigger_product_id"=>$casco->id,"offer_product_id"=>$acc->id,"trigger_category_id"=>null,"offer_category_id"=>null],["discount_percent"=>25,"priority"=>100,"is_active"=>true]);
echo "PRUEBA: ".$casco->slug." / ".$acc->slug.PHP_EOL;
echo "Reglas activas: ".App\Models\UpsellRule::where("is_active",true)->count().PHP_EOL;
echo "Ruta saved-card: ".(collect(Route::getRoutes())->contains(fn($r) => $r->uri() === "api/v1/payments/stripe/saved-card") ? "OK" : "FALTA").PHP_EOL;
'

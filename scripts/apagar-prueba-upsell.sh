#!/bin/bash
# Apaga los productos de prueba de $1 del upsell y su regla (no borra pedidos).
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
$PHP artisan tinker --execute='
$ids = App\Models\Product::whereIn("slug",["prueba-casco-1","prueba-accesorio-1"])->pluck("id");
App\Models\Product::whereIn("id",$ids)->update(["is_active"=>false]);
App\Models\UpsellRule::whereIn("trigger_product_id",$ids)->update(["is_active"=>false]);
echo "Productos de prueba apagados: ".$ids->count().PHP_EOL;
'

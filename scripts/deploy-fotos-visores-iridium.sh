#!/bin/bash
# Fotos de los visores Iridium (Púrpura, Violeta, Azul Eléctrico, Azul Turquesa, Dorado,
# Naranja): reemplaza las texturas de color por una foto de visor de ese color
# (pedido de Gerson, 26-nov-2026). Solo cambia image_url de esos 6 visores; las fotos
# anteriores quedan en el disco (no se borran) para volver atrás.
set -u
COMMIT="b86674fffda70a86abd6847ce89853a7318e5690"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/scripts/visores"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
DIR="$APP/storage/app/public/visors"
cd "$APP" || { echo "No existe $APP"; exit 1; }
mkdir -p "$DIR"

declare -A VISOR=(
  ["iridium-purpura"]="32698d5a-c320-46c8-a278-18652178d1e5"
  ["iridium-violeta"]="6ab05638-8c0a-46bd-b10f-c093be051821"
  ["iridium-azul-electrico"]="5fd911a4-92c1-4400-bd2d-459c797dddd6"
  ["iridium-azul-turquesa"]="c59bdcac-4dca-47bd-ba72-05bf4c50bc94"
  ["iridium-dorado"]="c322a778-392e-419a-b4b2-e8eddcf62ecd"
  ["iridium-naranja"]="e044f672-a0e5-4284-8d87-a3c92f92ca27"
)

for n in "${!VISOR[@]}"; do
  destino="$DIR/$n-20261126.jpg"
  curl -fsSL "$RAW/$n.jpg" -o "$destino.tmp" && mv "$destino.tmp" "$destino" || { echo "FALLO descarga $n"; exit 1; }
  echo "Foto $n: $(du -k "$destino" | cut -f1) KB"
done

$PHP artisan tinker --execute='
$mapa = [
  "32698d5a-c320-46c8-a278-18652178d1e5" => "visors/iridium-purpura-20261126.jpg",
  "6ab05638-8c0a-46bd-b10f-c093be051821" => "visors/iridium-violeta-20261126.jpg",
  "5fd911a4-92c1-4400-bd2d-459c797dddd6" => "visors/iridium-azul-electrico-20261126.jpg",
  "c59bdcac-4dca-47bd-ba72-05bf4c50bc94" => "visors/iridium-azul-turquesa-20261126.jpg",
  "c322a778-392e-419a-b4b2-e8eddcf62ecd" => "visors/iridium-dorado-20261126.jpg",
  "e044f672-a0e5-4284-8d87-a3c92f92ca27" => "visors/iridium-naranja-20261126.jpg",
];
foreach ($mapa as $id => $ruta) {
  $v = App\Models\VisorColor::find($id);
  if (! $v) { echo "NO EXISTE visor $id".PHP_EOL; continue; }
  if (! Storage::disk("public")->exists($ruta)) { echo "FALTA archivo $ruta".PHP_EOL; continue; }
  $antes = $v->image_url;
  $v->update(["image_url" => $ruta]);
  echo $v->name.": ".$antes." -> ".$ruta.PHP_EOL;
}
'
$PHP artisan cache:clear >/dev/null 2>&1 || true

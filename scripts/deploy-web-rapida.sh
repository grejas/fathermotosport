#!/bin/bash
# Web más rápida: variantes WebP de imágenes y compresión de videos (ffmpeg estático).
set -u
COMMIT="db4497d2c431c26671055f4b1596ba28a2b8e81f"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
declare -A BASE=(
  ["routes/web.php"]="3eb986b86237026881e06b2369e60c4c"
  ["routes/console.php"]="dbf5ef25ee249863c25a66a12049d6b8"
  ["config/services.php"]="97e1a29ffddb93bcf668916e7d0dab92"
)
NUEVOS=(
  app/Http/Controllers/Media/VarianteDeImagenController.php
  app/Services/VariantesDeImagen.php
  app/Console/Commands/OptimizarVideos.php
)
for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" | cut -d' ' -f1)
  [ "$actual" = "${BASE[$f]}" ] || { echo "ABORTO: $f no coincide con main. No se tocó nada."; exit 1; }
done
mkdir -p app/Http/Controllers/Media
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  [ -f "$f" ] && cp "$f" "$f.bak-webrapida"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
  $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f; restaurando"; [ -f "$f.bak-webrapida" ] && cp "$f.bak-webrapida" "$f"; exit 1; }
done
$PHP artisan optimize:clear
$PHP artisan route:cache

echo "== Prueba de variante WebP"
IMG=$(ls storage/app/public/products/*.jpg 2>/dev/null | head -1 | sed 's#storage/app/public/##')
URL="https://api.fathermotosport.com/storage/v/640/$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$IMG" 2>/dev/null || echo "$IMG").webp"
curl -s -o /dev/null -w "1ª vez: %{http_code} %{content_type} %{size_download} bytes\n" "$URL"
curl -s -o /dev/null -w "2ª vez: %{http_code} %{content_type} %{size_download} bytes\n" "$URL"
echo "original: $(stat -c %s "storage/app/public/$IMG") bytes"

echo "== Espacio"
du -sh storage/app/public 2>/dev/null; quota -s 2>/dev/null | tail -2 || true

echo "== ffmpeg"
if [ ! -x "$HOME/bin/ffmpeg" ]; then
  mkdir -p "$HOME/bin" "$HOME/tmp-ffmpeg"
  curl -fsSL https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o "$HOME/tmp-ffmpeg/ff.tar.xz" \
    && tar -xJf "$HOME/tmp-ffmpeg/ff.tar.xz" -C "$HOME/tmp-ffmpeg" \
    && cp "$HOME"/tmp-ffmpeg/ffmpeg-*-static/ffmpeg "$HOME"/tmp-ffmpeg/ffmpeg-*-static/ffprobe "$HOME/bin/" \
    && chmod +x "$HOME/bin/ffmpeg" "$HOME/bin/ffprobe"
  rm -rf "$HOME/tmp-ffmpeg"
fi
"$HOME/bin/ffmpeg" -version 2>/dev/null | head -1 || echo "ffmpeg NO disponible: los videos quedan como están."

if [ -x "$HOME/bin/ffmpeg" ]; then
  echo "== Comprimiendo videos en segundo plano (log: storage/logs/videos.log)"
  nohup nice -n 15 $PHP artisan media:optimizar-videos --limite=500 > storage/logs/videos.log 2>&1 &
  sleep 20; cat storage/logs/videos.log
fi

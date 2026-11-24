#!/bin/bash
# Solo lectura: qué soporta el servidor para optimizar imágenes y videos.
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
echo "== GD"; $PHP -r 'print_r(array_intersect_key(gd_info(), array_flip(["GD Version","WebP Support","JPEG Support","PNG Support","AVIF Support"])));'
echo "== Imagick"; $PHP -r 'echo extension_loaded("imagick") ? "si\n" : "no\n";'
echo "== memory_limit"; $PHP -r 'echo ini_get("memory_limit"), "\n";'
echo "== ffmpeg"; which ffmpeg || echo "no"
echo "== public"; ls -la public | head -20
echo "== .htaccess"; cat public/.htaccess 2>/dev/null | head -30
echo "== videos"; find storage/app/public -name '*.mp4' -printf '%s %p\n' 2>/dev/null | sort -rn | head -20
echo "== imagenes"; find storage/app/public -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' \) | wc -l
du -sh storage/app/public/products storage/app/public/banners 2>/dev/null

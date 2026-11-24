#!/bin/bash
# Corta la corrida anterior, actualiza el comando y reintenta: un video en primer plano
# (para ver que anda) y el resto en segundo plano.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
pkill -f "media:optimizar-videos" 2>/dev/null; pkill -f "$HOME/bin/ffmpeg" 2>/dev/null; sleep 2
F=app/Console/Commands/OptimizarVideos.php
curl -fsSL "https://raw.githubusercontent.com/grejas/fathermotosport/3f6669d023303962a1724d1a04109e93afcb7e4b/fathermotosport-api/$F" -o "$F.tmp" && mv "$F.tmp" "$F"
$PHP -l "$F" >/dev/null || exit 1
rm -f storage/app/videos-optimizados.json
find storage/app/public -name '*.opt.mp4' -delete
$PHP artisan optimize:clear >/dev/null
echo "== 1 video de prueba"; time $PHP artisan media:optimizar-videos --limite=1
nohup nice -n 15 $PHP artisan media:optimizar-videos --limite=500 > storage/logs/videos.log 2>&1 &
sleep 60; echo "== segundo plano"; cat storage/logs/videos.log

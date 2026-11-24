#!/bin/bash
# Instala ffmpeg estático en ~/bin (sin xz: se descomprime con Python) y arranca la
# compresión de videos en segundo plano.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
if [ ! -x "$HOME/bin/ffmpeg" ]; then
  mkdir -p "$HOME/bin" "$HOME/tmp-ffmpeg"
  curl -fsSL https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o "$HOME/tmp-ffmpeg/ff.tar.xz" || { echo "FALLO descarga"; exit 1; }
  PY=$(command -v python3 || command -v python || echo "")
  [ -n "$PY" ] || { echo "Sin python para descomprimir"; exit 1; }
  "$PY" - "$HOME/tmp-ffmpeg/ff.tar.xz" "$HOME/tmp-ffmpeg" <<'PY'
import sys, tarfile
with tarfile.open(sys.argv[1], 'r:xz') as t:
    for m in t.getmembers():
        if m.name.endswith('/ffmpeg') or m.name.endswith('/ffprobe'):
            m.name = m.name.split('/')[-1]
            t.extract(m, sys.argv[2])
PY
  cp "$HOME/tmp-ffmpeg/ffmpeg" "$HOME/tmp-ffmpeg/ffprobe" "$HOME/bin/" && chmod +x "$HOME/bin/ffmpeg" "$HOME/bin/ffprobe"
  rm -rf "$HOME/tmp-ffmpeg"
fi
"$HOME/bin/ffmpeg" -version | head -1 || { echo "ffmpeg NO disponible"; exit 1; }
if pgrep -f "media:optimizar-videos" >/dev/null; then echo "Ya está corriendo."; else
  nohup nice -n 15 $PHP artisan media:optimizar-videos --limite=500 > storage/logs/videos.log 2>&1 &
fi
sleep 45; cat storage/logs/videos.log

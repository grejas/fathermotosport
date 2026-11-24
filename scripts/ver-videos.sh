#!/bin/bash
# Solo lectura: avance de la compresión de videos.
cd /home/u206357267/domains/fathermotosport.com/api || exit 1
tail -40 storage/logs/videos.log 2>/dev/null
pgrep -fa "optimizar-videos|ffmpeg" | head -3
du -sh storage/app/public/products/videos storage/app/public/banners/videos storage/app/originales 2>/dev/null

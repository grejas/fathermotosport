#!/bin/bash
# Solo nombres de variables y fechas, nunca valores.
cd /home/u206357267/domains/fathermotosport.com || exit 1
for f in api/.env public_html/.env api/.env.production; do
  [ -f "$f" ] && echo "$f modificado: $(date -r "$f" '+%Y-%m-%d %H:%M') claves MP: $(grep -io '^ *MERCADO[A-Z_]*' "$f" | tr '\n' ' ')"
done
find /home/u206357267/domains -maxdepth 4 -name '.env*' -newermt '-2 hours' 2>/dev/null | sed 's/^/reciente: /'

#!/bin/bash
# Muestra los últimos errores de Stripe del log de la API (solo lectura).
APP="/home/u206357267/domains/fathermotosport.com/api"
cd "$APP" || exit 1
L=$(ls -t storage/logs/*.log 2>/dev/null | head -1)
echo "Log: $L"
grep -n "Stripe" "$L" | tail -5 | cut -c1-1500

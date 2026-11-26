#!/bin/bash
# Correos a clientes desde ventas@ (pedido de Gerson, 26-nov-2026) + reenvío a FMS-0065
# desde la casilla de ventas (el primero salió por error desde el Gmail personal).
# Antes de pisar cada archivo verifica que el del servidor sea idéntico a main
# (35f03a5): si alguno difiere, no toca nada. Copias .bak-ventas para volver atrás.
set -u
COMMIT="bf6469188773d5c5f0f9b65a194dc50bf25fa47b"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

declare -A BASE=(
  ["app/Filament/Resources/OrderResource.php"]="bd3afbe083536029f0b28450582c52ee"
  ["app/Services/EmailService.php"]="248cbfb92c5e97ad652bae37cadd6195"
  ["config/mail.php"]="18601a927b48d38337ba2077426a7d60"
)
NUEVOS=(
  app/Console/Commands/PedirDatosEnvio.php
  app/Mail/ConfirmarDatosEnvioMail.php
  lang/en/confirmar_datos_envio.php
  lang/es/confirmar_datos_envio.php
  lang/pt/confirmar_datos_envio.php
  resources/views/emails/confirmar-datos-envio.blade.php
)

for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" 2>/dev/null | cut -d' ' -f1)
  if [ "$actual" != "${BASE[$f]}" ]; then
    echo "ABORTO: $f en el servidor no coincide con main (servidor $actual, main ${BASE[$f]}). No se tocó nada."
    exit 1
  fi
done
echo "Servidor = main en los ${#BASE[@]} archivos. Desplegando…"

for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  mkdir -p "$(dirname "$f")"
  [ -f "$f" ] && cp "$f" "$f.bak-ventas"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
done
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  case "$f" in *.blade.php) ;; *) $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; } ;; esac
done

$PHP artisan optimize:clear >/dev/null
$PHP artisan route:cache >/dev/null
$PHP artisan filament:cache-components >/dev/null 2>&1 || true
echo "Remitente ventas: $($PHP artisan tinker --execute='echo config("mail.ventas.name")." <".config("mail.ventas.address").">";')"

# Reenvío a FMS-0065 desde ventas@ (se imprime solo el asunto y el destinatario).
$PHP artisan pedidos:pedir-datos FMS-0065 --nota="Te escribimos desde nuestro correo oficial de ventas. Si ya respondiste a nuestro mensaje anterior no hace falta repetirlo; si no, por favor responde a este correo."

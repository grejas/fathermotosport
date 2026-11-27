#!/bin/bash
# Riesgo alto de la auditoría legal (rama feat/riesgo-alto, pedido de Gerson, 27-nov-2026):
# política con desistimiento de 14 días, función de desistimiento (POST /withdrawals + panel
# "Desistimientos" + acuse por correo) y limpieza de certificaciones que no corresponden.
# Antes de pisar routes/api.php verifica que el del servidor sea idéntico al de main;
# si difiere, no toca nada. Guarda copia .bak-riesgo-alto.
set -u
COMMIT="a0aae2a66b6a794d56b4d3050ee7c1ceed1de8db"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

declare -A BASE=(
  ["routes/api.php"]="be078aa182f7a7f546cefab3be4d2483"
)
NUEVOS=(
  app/Filament/Resources/WithdrawalRequestResource.php
  app/Filament/Resources/WithdrawalRequestResource/Pages/ListWithdrawalRequests.php
  app/Http/Controllers/Api/WithdrawalController.php
  app/Mail/WithdrawalReceivedMail.php
  app/Models/WithdrawalRequest.php
  database/migrations/2026_11_27_100001_returns_policy_right_of_withdrawal.php
  database/migrations/2026_11_27_100002_create_withdrawal_requests_table.php
  database/migrations/2026_11_27_100003_clear_wrong_certifications.php
  lang/en/desistimiento.php
  lang/es/desistimiento.php
  lang/pt/desistimiento.php
  resources/views/emails/desistimiento.blade.php
)

for f in "${!BASE[@]}"; do
  actual=$(md5sum "$f" 2>/dev/null | cut -d' ' -f1)
  if [ "$actual" != "${BASE[$f]}" ]; then
    echo "ABORTO: $f en el servidor no coincide con main (servidor $actual, main ${BASE[$f]}). No se tocó nada."
    exit 1
  fi
done
echo "Servidor = main. Desplegando…"

for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  mkdir -p "$(dirname "$f")"
  [ -f "$f" ] && cp "$f" "$f.bak-riesgo-alto"
  curl -fsSL "$RAW/$f" -o "$f.tmp" && mv "$f.tmp" "$f" || { echo "FALLO descarga $f"; exit 1; }
done
for f in "${!BASE[@]}" "${NUEVOS[@]}"; do
  case "$f" in *.blade.php) ;; *) $PHP -l "$f" >/dev/null || { echo "ERROR de sintaxis en $f"; exit 1; } ;; esac
done

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan route:cache
$PHP artisan view:clear
$PHP artisan filament:cache-components 2>/dev/null || true

# Verificación (solo lectura, sin datos de clientes).
$PHP artisan route:list --path=withdrawals 2>/dev/null | grep -i withdrawals || echo "FALTA la ruta /withdrawals"
$PHP artisan tinker --execute='
echo "Tabla withdrawal_requests: ".(Schema::hasTable("withdrawal_requests") ? "OK" : "FALTA").PHP_EOL;
$s = App\Models\ShippingReturnsSetting::find(1);
echo "Política con 14 días: ".(str_contains((string) ($s?->page_body["es"] ?? ""), "14 días") ? "OK" : "revisar").PHP_EOL;
echo "Productos no-casco con norma de casco: ".App\Models\Product::whereNotNull("certification")->whereHas("category", fn($q) => $q->where("slug", "!=", "cascos"))->where(fn($q) => $q->where("certification", "like", "%ECE%")->orWhere("certification", "like", "%DOT%"))->count().PHP_EOL;
'

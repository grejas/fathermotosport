#!/bin/bash
# Black Week 2026 · día 1 (expectativa, sin descuentos). Pedido de Gerson, 26-nov-2026.
# 1) Remitente de las campañas manuales: ventas@ (regla 7). Verifica que CorreoCampana.php
#    del servidor sea idéntico a main antes de pisarlo; deja copia .bak-ventas.
# 2) Crea la campaña del día 1 (idempotente por dedupe_key) y la programa para hoy 19:00 (La Paz).
# 3) Manda UNA prueba a ventas@fathermotosport.com. No envía nada más.
set -u
COMMIT="7af52522a0f7dae227dee5fcbdd6bc8da17e89cf"
RAW="https://raw.githubusercontent.com/grejas/fathermotosport/$COMMIT/fathermotosport-api"
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }

F="app/Mail/CorreoCampana.php"
NUEVO_MD5="1ef307cff216214d0b9b743772fc3248"
actual=$(md5sum "$F" | cut -d' ' -f1)
if [ "$actual" = "$NUEVO_MD5" ]; then
  echo "Remitente ventas@ ya desplegado."
elif [ "$actual" = "09007975198f7e19f709b946b5dc0eb6" ]; then
  cp "$F" "$F.bak-ventas"
  curl -fsSL "$RAW/$F" -o "$F.tmp" && mv "$F.tmp" "$F" || { echo "FALLO descarga $F"; exit 1; }
  [ "$(md5sum "$F" | cut -d' ' -f1)" = "$NUEVO_MD5" ] || { echo "md5 inesperado; restauro"; cp "$F.bak-ventas" "$F"; exit 1; }
  $PHP -l "$F" >/dev/null || { echo "Error de sintaxis; restauro"; cp "$F.bak-ventas" "$F"; exit 1; }
  echo "Remitente ventas@ desplegado en $F (copia en $F.bak-ventas)."
else
  echo "ABORTO: $F en el servidor no coincide con main ($actual). No se tocó nada."; exit 1
fi

DATOS="eyJub21icmUiOiAiQmxhY2sgV2VlayAyMDI2IMK3IETDrWEgMSDCtyBNYcOxYW5hIEVtcGllemEiLCAiZmVjaGEiOiAiMjYvMTEvMjAyNiAxOTowMCIsICJhc3VudG8iOiAiTWHDsWFuYSBhcnJhbmNhIGVsIEJsYWNrIEZyaWRheSBlbiBGYXRoZXJNb3RvU3BvcnQiLCAicHJlaGVhZGVyIjogIkRlbCB2aWVybmVzIDI3IGFsIGx1bmVzIDMwLCB1biBkZXNjdWVudG8gZGlzdGludG8gY2FkYSBkw61hLiBUZSBjb250YW1vcyBxdcOpIHRvY2EuIiwgInRpdHVsYXIiOiAiQ3VlbnRhIHJlZ3Jlc2l2YSIsICJjdWVycG8iOiAiPHA+TWHDsWFuYSwgdmllcm5lcyAyNywgc2UgYWJyZW4gbG9zIGRlc2N1ZW50b3MgZGUgQmxhY2sgRnJpZGF5IGVuIEZhdGhlck1vdG9TcG9ydC4gVGUgZXNjcmliaW1vcyBob3kgcGFyYSBxdWUgbGxlZ3VlcyBjb24gdHUgbGlzdGEgbGlzdGEuPC9wPjxwPkNhZGEgZMOtYSBhYnJpbW9zIHVuYSBjYXRlZ29yw61hIGRpc3RpbnRhLCBoYXN0YSBlbCBsdW5lcyAzMCBhIGxhcyAyMzo1OS4gTWlyYSBlbCBjYWxlbmRhcmlvIHkgYW5vdGEgcXXDqSB0ZSBmYWx0YTogY2FzY28sIHJvcGEgcGFyYSBlbCBmcsOtbyBvIGx1Y2VzIHBhcmEgdHUgbW90by48L3A+PHA+PHN0cm9uZz7igKIgQ2FzY29zIGRlcG9ydGl2b3M8L3N0cm9uZz46IFZJRSAyNzxicj48c3Ryb25nPuKAoiBIb29kaWVzIHJlZmxlY3RpdmFzLCBndWFudGVzIHkgY2hhcXVldGFzPC9zdHJvbmc+OiBTw4FCIDI4PGJyPjxzdHJvbmc+4oCiIEFsYXMgTEVEIHBhcmEgdHUgbW90bzwvc3Ryb25nPjogRE9NIDI5PGJyPjxzdHJvbmc+4oCiIMOabHRpbW8gZMOtYTogdG9kbyBqdW50bzwvc3Ryb25nPjogTFVOIDMwPC9wPjxwPk1hw7FhbmEgYSBsYXMgOTowMCB0ZSBsbGVnYSBlbCBwcmltZXIgZGVzY3VlbnRvLiBOb3MgdmVtb3MgZW4gbGEgcnV0YS48L3A+IiwgImJvdG9uX3RleHRvIjogIkF2w61zYW1lIHBvciBXaGF0c0FwcCIsICJib3Rvbl91cmwiOiAiaHR0cHM6Ly93YS5tZS81OTE2ODczNjM4ND90ZXh0PUhvbGElMjElMjBRdWllcm8lMjBxdWUlMjBtZSUyMGF2aXNlbiUyMGN1YW5kbyUyMGVtcGllY2UlMjBlbCUyMEJsYWNrJTIwRnJpZGF5JTIwZGUlMjBGYXRoZXJNb3RvU3BvcnQuIiwgImltYWdlbl9hbHQiOiAiQmxhY2sgRnJpZGF5IMK3IDI3IGFsIDMwIG5vdiDCtyBGYXRoZXJNb3RvU3BvcnQgQmxhY2sgV2VlayJ9" $PHP artisan tinker --execute='
$d = json_decode(base64_decode(getenv("DATOS")), true);
$tz = "America/La_Paz";
$campana = App\Models\MailCampaign::firstOrCreate(
  ["dedupe_key" => "manual:black-week-2026-d1"],
  [
    "type" => App\Models\MailCampaign::TIPO_MANUAL,
    "status" => App\Models\MailCampaign::BORRADOR,
    "name" => $d["nombre"],
    "scheduled_at" => Carbon\Carbon::createFromFormat("d/m/Y H:i", $d["fecha"], $tz)->utc(),
    "payload" => App\Services\CampanaManual::limpiarPayload([
      "contenido" => ["es" => [
        "asunto" => $d["asunto"], "preheader" => $d["preheader"], "titular" => $d["titular"],
        "cuerpo" => $d["cuerpo"], "boton_texto" => $d["boton_texto"], "boton_url" => $d["boton_url"],
        "imagen_alt" => $d["imagen_alt"],
      ]],
      "imagen" => null,
      "segmento" => [],
    ]),
  ]
);
$s = app(App\Services\CampanaManual::class);
if ($campana->status === App\Models\MailCampaign::BORRADOR) { $campana = $s->programar($campana); }
echo "Campaña #".$campana->id." · ".$campana->status." · ".$campana->scheduled_at->timezone($tz)->format("Y-m-d H:i")." · destinatarios hoy: ".App\Models\User::destinatariosDePromociones()->count().PHP_EOL;
$prueba = $s->correoDePrueba($campana);
$env = $prueba->envelope();
echo "Prueba: de ".($env->from?->address ?? "(por defecto)")." · responder a ".implode(",", array_map(fn ($a) => $a->address, $env->replyTo))." · asunto: ".$env->subject.PHP_EOL;
Illuminate\Support\Facades\Mail::to(config("mail.ventas.address"))->send($prueba);
echo "Prueba enviada a ".config("mail.ventas.address").PHP_EOL;
echo "== Categorías (para los días 27 a 30):".PHP_EOL;
foreach (App\Models\Category::orderBy("name")->get() as $k) { echo "   #".$k->id." ".$k->name.PHP_EOL; }
'

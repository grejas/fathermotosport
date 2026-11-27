#!/bin/bash
# Black Week 2026 · días 2 a 5 (27–30 nov). Pedido de Gerson, 26-nov-2026.
# Descuento: el cupón del popup (10 % a 25 %, uno por persona); no crea cupones ni toca precios.
# Crea las 4 campañas (idempotentes por dedupe_key), las programa y manda una prueba de cada una
# a ventas@. El día 4 (alas LED a USD 95) solo se programa si la web tiene ese precio; si no,
# queda en borrador.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
grep -q "mail.ventas.address" app/Mail/CorreoCampana.php || { echo "ABORTO: el remitente ventas@ no está desplegado."; exit 1; }

DATOS="eyIyIjogeyJub21icmUiOiAiQmxhY2sgV2VlayAyMDI2IMK3IETDrWEgMiDCtyBCbGFjayBGcmlkYXkiLCAiZmVjaGEiOiAiMjcvMTEvMjAyNiAwOTowMCIsICJhc3VudG8iOiAiQkxBQ0sgRlJJREFZOiB0dSBjYXNjbyBudWV2byBjb24gaGFzdGEgMjUgJSBPRkYiLCAicHJlaGVhZGVyIjogIlR1IGN1cMOzbiBkZSAxMCAlIGEgMjUgJSB0ZSBlc3BlcmEgZW4gbGEgd2ViLiBBR1YsIExTMiwgS1lUIHkgbcOhcy4iLCAidGl0dWxhciI6ICJFbCBjYXNjbyBxdWUgbWVyZWNlcyIsICJjdWVycG8iOiAiPHA+SG95IGVzIEJsYWNrIEZyaWRheS4gU2kgbGxldmFzIG1lc2VzIG1pcmFuZG8gdW4gY2FzY28sIGVzdGUgZXMgZWwgZMOtYSBwYXJhIHF1ZWTDoXJ0ZWxvLjwvcD48cD5UZW5lbW9zIGNhc2NvcyBjb24gZGlzZcOxb3MgZGUgcGlsb3RvcyBkZSBNb3RvR1AgeSBjYXNjb3MgZGVwb3J0aXZvcyBwYXJhIHRvZG9zIGxvcyBkw61hcy4gU2kgbm8gc2FiZXMgcXXDqSB0YWxsYSB0ZSB2YSwgZXNjcsOtYmVub3M6IHRlIGF5dWRhbW9zIGEgbWVkaXJ0ZSBhbnRlcyBkZSBjb21wcmFyLjwvcD48cD48c3Ryb25nPuKAoiBBR1YgwrcgZGlzZcOxbyBKb2FuIE1pcjwvc3Ryb25nPjogMTDigJMyNSAlPGJyPjxzdHJvbmc+4oCiIEFHViDCtyBSb3NzaSBNaXNhbm8gMjAyMTwvc3Ryb25nPjogMTDigJMyNSAlPGJyPjxzdHJvbmc+4oCiIExTMiDCtyBLWVQgwrcgZ2FtYSBkZXBvcnRpdmE8L3N0cm9uZz46IDEw4oCTMjUgJTwvcD48cD5FbnRyYSBhIGZhdGhlcm1vdG9zcG9ydC5jb20sIGRlamEgdHUgY29ycmVvIGVuIGxhIHZlbnRhbmEgZGUgYmllbnZlbmlkYSB5IGRlc2N1YnJlIHR1IGN1cMOzbiBkZSAxMCAlIGEgMjUgJS4gU2kgeWEgbG8gdGllbmVzIHkgbm8gbG8gdXNhc3RlLCBlcyBlbCBtaXNtby4gVW4gY3Vww7NuIHBvciBwZXJzb25hIHkgdW4gZGVzY3VlbnRvIHBvciBjb21wcmEuPC9wPjxwPkxhIEJsYWNrIFdlZWsgc2lndWUgaGFzdGEgZWwgbHVuZXMgMzAsIHBlcm8gbGFzIHRhbGxhcyBzZSB2YW4gcHJpbWVyby48L3A+IiwgImJvdG9uX3RleHRvIjogIlZlciBjYXNjb3MiLCAiYm90b25fdXJsIjogImh0dHBzOi8vd3d3LmZhdGhlcm1vdG9zcG9ydC5jb20vY2F0YWxvZz9jYXRlZ29yeT0xJnV0bV9zb3VyY2U9ZW1haWwmdXRtX21lZGl1bT1wYW5lbCZ1dG1fY2FtcGFpZ249YmxhY2std2Vlay0yMDI2JnV0bV9jb250ZW50PWQyLWJvdG9uIiwgImltYWdlbl9hbHQiOiAiQ2FzY29zIGRlcG9ydGl2b3MgwrcgRmF0aGVyTW90b1Nwb3J0IEJsYWNrIFdlZWsifSwgIjMiOiB7Im5vbWJyZSI6ICJCbGFjayBXZWVrIDIwMjYgwrcgRMOtYSAzIMK3IEJsYWNrIFdlZWsgwrcgRMOtYSAzIiwgImZlY2hhIjogIjI4LzExLzIwMjYgMTE6MDAiLCAiYXN1bnRvIjogIkhvb2RpZXMsIGd1YW50ZXMgeSBjaGFxdWV0YXMgY29uIGhhc3RhIDI1ICUgT0ZGIiwgInByZWhlYWRlciI6ICJFcXXDrXBhdGUgY29tcGxldG8gZXN0ZSBmaW4gZGUgc2VtYW5hIGNvbiB0dSBjdXDDs24gZGUgQmxhY2sgV2Vlay4iLCAidGl0dWxhciI6ICJFcXXDrXBhdGUgY29tcGxldG8iLCAiY3VlcnBvIjogIjxwPlVuIGJ1ZW4gY2FzY28gZXMgbGEgbWl0YWQgZGVsIGVxdWlwby4gTGEgb3RyYSBtaXRhZCBzb24gdHVzIG1hbm9zIHkgdHUgZXNwYWxkYS48L3A+PHA+RXN0ZSBzw6FiYWRvIHRvY2Egcm9wYTogaG9vZGllcyByZWZsZWN0aXZhcywgZ3VhbnRlcyB5IGNoYXF1ZXRhcyBkZSBtb3RvLiBTaSB0ZSBsYXMgdmFzIGEgY29tcHJhciBlbiBhbGfDum4gbW9tZW50byBkZWwgYcOxbywgcXVlIHNlYSBjb24gdHUgY3Vww7NuIGRlIEJsYWNrIFdlZWsuPC9wPjxwPjxzdHJvbmc+4oCiIEhvb2RpZXMgcmVmbGVjdGl2YXM8L3N0cm9uZz46IDEw4oCTMjUgJTxicj48c3Ryb25nPuKAoiBHdWFudGVzIGRlIG1vdG88L3N0cm9uZz46IDEw4oCTMjUgJTxicj48c3Ryb25nPuKAoiBDaGFxdWV0YXMgZGUgbW90bzwvc3Ryb25nPjogMTDigJMyNSAlPC9wPjxwPkVudHJhIGEgZmF0aGVybW90b3Nwb3J0LmNvbSwgZGVqYSB0dSBjb3JyZW8gZW4gbGEgdmVudGFuYSBkZSBiaWVudmVuaWRhIHkgZGVzY3VicmUgdHUgY3Vww7NuIGRlIDEwICUgYSAyNSAlLiBTaSB5YSBsbyB0aWVuZXMgeSBubyBsbyB1c2FzdGUsIGVzIGVsIG1pc21vLiBVbiBjdXDDs24gcG9yIHBlcnNvbmEgeSB1biBkZXNjdWVudG8gcG9yIGNvbXByYS48L3A+PHA+VGUgcXVlZGFuIHRyZXMgZMOtYXMgZGUgQmxhY2sgV2Vlay4gTWHDsWFuYTogZWwga2l0IHBhcmEgcm9kYXIgZGUgbm9jaGUuPC9wPiIsICJib3Rvbl90ZXh0byI6ICJWZXIgcm9wYSBkZSBtb3RvIiwgImJvdG9uX3VybCI6ICJodHRwczovL3d3dy5mYXRoZXJtb3Rvc3BvcnQuY29tL2NhdGFsb2c/dXRtX3NvdXJjZT1lbWFpbCZ1dG1fbWVkaXVtPXBhbmVsJnV0bV9jYW1wYWlnbj1ibGFjay13ZWVrLTIwMjYmdXRtX2NvbnRlbnQ9ZDMtYm90b24iLCAiaW1hZ2VuX2FsdCI6ICJSb3BhIGRlIG1vdG8gwrcgRmF0aGVyTW90b1Nwb3J0IEJsYWNrIFdlZWsifSwgIjQiOiB7Im5vbWJyZSI6ICJCbGFjayBXZWVrIDIwMjYgwrcgRMOtYSA0IMK3IEJsYWNrIFdlZWsgwrcgRMOtYSA0IiwgImZlY2hhIjogIjI5LzExLzIwMjYgMTg6MDAiLCAiYXN1bnRvIjogIlR1IG1vdG8sIGRlIG5vY2hlOiBhbGFzIExFRCBlbiBwcmV2ZW50YSBhIFVTRCA5NSIsICJwcmVoZWFkZXIiOiAiOSBkaXNlw7FvcyBwYXJhIGVsIHBpc28gZGUgdHUgbW90by4gUHJlY2lvIGRlIHByZXZlbnRhIHBvciBCbGFjayBXZWVrLiIsICJ0aXR1bGFyIjogIkRlamEgdHUgZmlybWEgZW4gZWwgcGlzbyIsICJjdWVycG8iOiAiPHA+RG9taW5nbywgcGFyYSBsb3MgcXVlIHNhbGVuIGEgcm9kYXIgZGUgbm9jaGUuPC9wPjxwPkxhcyBhbGFzIExFRCBwcm95ZWN0YW4gdW4gZGlzZcOxbyBpbHVtaW5hZG8gZW4gZWwgcGlzbywgZGViYWpvIGRlIHR1IG1vdG8uIExsZWdhcyBhIHVuIGVuY3VlbnRybyB5IHRvZG9zIG1pcmFuIGVsIHBpc28uIEhheSA5IGRpc2XDsW9zOiBhenVsLCBibGFuY28sIGRvcmFkbywgcm9qbywgcm9zYWRvLCB2ZXJkZSwgRHJhZ8OzbiBBenVsLCBGw6luaXggQW1hcmlsbG8geSBMb2JvIExpbGEuPC9wPjxwPjxzdHJvbmc+4oCiIEFsYXMgTEVEIGRlY29yYXRpdmFzPC9zdHJvbmc+OiBVU0QgOTU8YnI+PHN0cm9uZz7igKIgQmFsYWNsYXZhczwvc3Ryb25nPjogMTDigJMyNSAlPGJyPjxzdHJvbmc+4oCiIFByb3RlY3RvcmVzIGRlIG1hbmdhczwvc3Ryb25nPjogMTDigJMyNSAlPC9wPjxwPkVsIHByZWNpbyBkZSBwcmV2ZW50YSBkZSBsYXMgYWxhcyBMRUQgeWEgdHJhZSBzdSBkZXNjdWVudG8uIFR1IGN1cMOzbiBkZSAxMCAlIGEgMjUgJSBsbyB1c2FzIGVuIG90cmEgY29tcHJhOiB1biBkZXNjdWVudG8gcG9yIGNvbXByYS48L3A+PHA+TWHDsWFuYSBlcyBDeWJlciBNb25kYXksIGVsIMO6bHRpbW8gZMOtYSBkZSBsYSBCbGFjayBXZWVrLjwvcD4iLCAiYm90b25fdGV4dG8iOiAiUmVzZXJ2YXIgbWlzIGFsYXMgTEVEIiwgImJvdG9uX3VybCI6ICJodHRwczovL3d3dy5mYXRoZXJtb3Rvc3BvcnQuY29tL2VzL3Byb2R1Y3QvYWxhcy1sZWQtZGVjb3JhdGl2by1pbHVtaW5hZG8/dXRtX3NvdXJjZT1lbWFpbCZ1dG1fbWVkaXVtPXBhbmVsJnV0bV9jYW1wYWlnbj1ibGFjay13ZWVrLTIwMjYmdXRtX2NvbnRlbnQ9ZDQtYm90b24iLCAiaW1hZ2VuX2FsdCI6ICJBbGFzIExFRCBkZWNvcmF0aXZhcyDCtyBGYXRoZXJNb3RvU3BvcnQgQmxhY2sgV2VlayJ9LCAiNSI6IHsibm9tYnJlIjogIkJsYWNrIFdlZWsgMjAyNiDCtyBEw61hIDUgwrcgQ3liZXIgTW9uZGF5IMK3IMOabHRpbW8gRMOtYSIsICJmZWNoYSI6ICIzMC8xMS8yMDI2IDEyOjAwIiwgImFzdW50byI6ICLDmmx0aW1vIGTDrWE6IGxhIEJsYWNrIFdlZWsgdGVybWluYSBob3kgYSBsYXMgMjM6NTkiLCAicHJlaGVhZGVyIjogIlVzYSBob3kgdHUgY3Vww7NuIGRlIDEwICUgYSAyNSAlLiBBbGFzIExFRCBhIFVTRCA5NSBlbiBwcmV2ZW50YS4iLCAidGl0dWxhciI6ICLDmmx0aW1vIGTDrWEiLCAiY3VlcnBvIjogIjxwPkhveSBhIGxhcyAyMzo1OSAoaG9yYSBkZSBCb2xpdmlhKSB0ZXJtaW5hIGxhIEJsYWNrIFdlZWsgZGUgRmF0aGVyTW90b1Nwb3J0LjwvcD48cD5TaSBkZWphc3RlIGFsZ28gZW4gZWwgY2Fycml0byBvIHRlIHF1ZWRhc3RlIHBlbnNhbmRvIGVuIHVuIGNhc2NvLCBlc3RlIGVzIGVsIMO6bHRpbW8gYXZpc28uPC9wPjxwPjxzdHJvbmc+4oCiIENhc2NvcyBkZXBvcnRpdm9zPC9zdHJvbmc+OiAxMOKAkzI1ICU8YnI+PHN0cm9uZz7igKIgSG9vZGllcywgZ3VhbnRlcyB5IGNoYXF1ZXRhczwvc3Ryb25nPjogMTDigJMyNSAlPGJyPjxzdHJvbmc+4oCiIEFsYXMgTEVEIGRlY29yYXRpdmFzPC9zdHJvbmc+OiBVU0QgOTU8L3A+PHA+RW50cmEgYSBmYXRoZXJtb3Rvc3BvcnQuY29tLCBkZWphIHR1IGNvcnJlbyBlbiBsYSB2ZW50YW5hIGRlIGJpZW52ZW5pZGEgeSBkZXNjdWJyZSB0dSBjdXDDs24gZGUgMTAgJSBhIDI1ICUuIFNpIHlhIGxvIHRpZW5lcyB5IG5vIGxvIHVzYXN0ZSwgZXMgZWwgbWlzbW8uIFVuIGN1cMOzbiBwb3IgcGVyc29uYSB5IHVuIGRlc2N1ZW50byBwb3IgY29tcHJhLjwvcD48cD5HcmFjaWFzIHBvciByb2RhciBjb24gbm9zb3Ryb3MgZXN0ZSBhw7FvLjwvcD4iLCAiYm90b25fdGV4dG8iOiAiQ29tcHJhciBhbnRlcyBkZSBsYXMgMjM6NTkiLCAiYm90b25fdXJsIjogImh0dHBzOi8vd3d3LmZhdGhlcm1vdG9zcG9ydC5jb20vY2F0YWxvZz91dG1fc291cmNlPWVtYWlsJnV0bV9tZWRpdW09cGFuZWwmdXRtX2NhbXBhaWduPWJsYWNrLXdlZWstMjAyNiZ1dG1fY29udGVudD1kNS1ib3RvbiIsICJpbWFnZW5fYWx0IjogIlRvZG8gRmF0aGVyTW90b1Nwb3J0IMK3IEZhdGhlck1vdG9TcG9ydCBCbGFjayBXZWVrIn19" $PHP artisan tinker --execute='
$todos = json_decode(base64_decode(getenv("DATOS")), true);
$tz = "America/La_Paz";
$s = app(App\Services\CampanaManual::class);
$alas = App\Models\Product::where("slug", "alas-led-decorativo-iluminado")->first();
$precioAlas = $alas ? (float) $alas->precioFinal()["final_price"] : null;
echo "Alas LED: ".($alas ? $alas->name." · precio final ".$precioAlas." · activo ".($alas->is_active ? "sí" : "no") : "no encontrado").PHP_EOL;
foreach ($todos as $n => $d) {
  $campana = App\Models\MailCampaign::firstOrCreate(
    ["dedupe_key" => "manual:black-week-2026-d".$n],
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
  $programar = $campana->status === App\Models\MailCampaign::BORRADOR;
  if ((string) $n === "4" && ($precioAlas === null || abs($precioAlas - 95.0) > 0.01 || ! $alas->is_active)) {
    $programar = false;
    echo "Día 4 queda en BORRADOR: el precio de las alas LED en la web no es USD 95 o el producto no está activo.".PHP_EOL;
  }
  if ($programar) { $campana = $s->programar($campana); }
  echo "Día ".$n." · campaña #".$campana->id." · ".$campana->status." · ".$campana->scheduled_at->timezone($tz)->format("Y-m-d H:i").PHP_EOL;
  Illuminate\Support\Facades\Mail::to(config("mail.ventas.address"))->send($s->correoDePrueba($campana));
}
echo "Pruebas enviadas a ".config("mail.ventas.address").PHP_EOL;
foreach (App\Models\MailCampaign::where("type", "manual")->orderBy("scheduled_at")->get() as $m) {
  echo "   #".$m->id." ".$m->name." · ".$m->status." · ".optional($m->scheduled_at)->timezone($tz)?->format("Y-m-d H:i").PHP_EOL;
}
'

#!/bin/bash
# Black Week 2026 · corrige días 4 y 5: alas LED a USD 84,99 (precio real de la web), sin "preventa".
# Actualiza el contenido de las campañas #7 y #8, programa la #7 y manda una prueba de cada una a ventas@.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
DATOS="eyI0IjogeyJhc3VudG8iOiAiVHUgbW90bywgZGUgbm9jaGU6IGFsYXMgTEVEIGEgVVNEIDg0LDk5IiwgInByZWhlYWRlciI6ICI5IGRpc2XDsW9zIHBhcmEgZWwgcGlzbyBkZSB0dSBtb3RvLiBNw6FzIGJhbGFjbGF2YXMgeSBtYW5nYXMgY29uIHR1IGN1cMOzbi4iLCAidGl0dWxhciI6ICJEZWphIHR1IGZpcm1hIGVuIGVsIHBpc28iLCAiY3VlcnBvIjogIjxwPkRvbWluZ28sIHBhcmEgbG9zIHF1ZSBzYWxlbiBhIHJvZGFyIGRlIG5vY2hlLjwvcD48cD5MYXMgYWxhcyBMRUQgcHJveWVjdGFuIHVuIGRpc2XDsW8gaWx1bWluYWRvIGVuIGVsIHBpc28sIGRlYmFqbyBkZSB0dSBtb3RvLiBMbGVnYXMgYSB1biBlbmN1ZW50cm8geSB0b2RvcyBtaXJhbiBlbCBwaXNvLiBIYXkgOSBkaXNlw7FvczogYXp1bCwgYmxhbmNvLCBkb3JhZG8sIHJvam8sIHJvc2FkbywgdmVyZGUsIERyYWfDs24gQXp1bCwgRsOpbml4IEFtYXJpbGxvIHkgTG9ibyBMaWxhLjwvcD48cD48c3Ryb25nPuKAoiBBbGFzIExFRCBkZWNvcmF0aXZhczwvc3Ryb25nPjogVVNEIDg0LDk5PGJyPjxzdHJvbmc+4oCiIEJhbGFjbGF2YXM8L3N0cm9uZz46IDEw4oCTMjUgJTxicj48c3Ryb25nPuKAoiBQcm90ZWN0b3JlcyBkZSBtYW5nYXM8L3N0cm9uZz46IDEw4oCTMjUgJTwvcD48cD5FbnRyYSBhIGZhdGhlcm1vdG9zcG9ydC5jb20sIGRlamEgdHUgY29ycmVvIGVuIGxhIHZlbnRhbmEgZGUgYmllbnZlbmlkYSB5IGRlc2N1YnJlIHR1IGN1cMOzbiBkZSAxMCAlIGEgMjUgJS4gU2kgeWEgbG8gdGllbmVzIHkgbm8gbG8gdXNhc3RlLCBlcyBlbCBtaXNtby4gVW4gY3Vww7NuIHBvciBwZXJzb25hIHkgdW4gZGVzY3VlbnRvIHBvciBjb21wcmEuPC9wPjxwPk1hw7FhbmEgZXMgQ3liZXIgTW9uZGF5LCBlbCDDumx0aW1vIGTDrWEgZGUgbGEgQmxhY2sgV2Vlay48L3A+IiwgImJvdG9uX3RleHRvIjogIlZlciBsYXMgYWxhcyBMRUQiLCAiYm90b25fdXJsIjogImh0dHBzOi8vd3d3LmZhdGhlcm1vdG9zcG9ydC5jb20vZXMvcHJvZHVjdC9hbGFzLWxlZC1kZWNvcmF0aXZvLWlsdW1pbmFkbz91dG1fc291cmNlPWVtYWlsJnV0bV9tZWRpdW09cGFuZWwmdXRtX2NhbXBhaWduPWJsYWNrLXdlZWstMjAyNiZ1dG1fY29udGVudD1kNC1ib3RvbiIsICJpbWFnZW5fYWx0IjogIkFsYXMgTEVEIGRlY29yYXRpdmFzIMK3IEZhdGhlck1vdG9TcG9ydCBCbGFjayBXZWVrIn0sICI1IjogeyJhc3VudG8iOiAiw5psdGltbyBkw61hOiBsYSBCbGFjayBXZWVrIHRlcm1pbmEgaG95IGEgbGFzIDIzOjU5IiwgInByZWhlYWRlciI6ICJVc2EgaG95IHR1IGN1cMOzbiBkZSAxMCAlIGEgMjUgJS4gRGVzcHXDqXMgdnVlbHZlbiBsb3MgcHJlY2lvcyBub3JtYWxlcy4iLCAidGl0dWxhciI6ICLDmmx0aW1vIGTDrWEiLCAiY3VlcnBvIjogIjxwPkhveSBhIGxhcyAyMzo1OSAoaG9yYSBkZSBCb2xpdmlhKSB0ZXJtaW5hIGxhIEJsYWNrIFdlZWsgZGUgRmF0aGVyTW90b1Nwb3J0LjwvcD48cD5TaSBkZWphc3RlIGFsZ28gZW4gZWwgY2Fycml0byBvIHRlIHF1ZWRhc3RlIHBlbnNhbmRvIGVuIHVuIGNhc2NvLCBlc3RlIGVzIGVsIMO6bHRpbW8gYXZpc28uPC9wPjxwPjxzdHJvbmc+4oCiIENhc2NvcyBkZXBvcnRpdm9zPC9zdHJvbmc+OiAxMOKAkzI1ICU8YnI+PHN0cm9uZz7igKIgSG9vZGllcywgZ3VhbnRlcyB5IGNoYXF1ZXRhczwvc3Ryb25nPjogMTDigJMyNSAlPGJyPjxzdHJvbmc+4oCiIEFsYXMgTEVEIGRlY29yYXRpdmFzPC9zdHJvbmc+OiBVU0QgODQsOTk8L3A+PHA+RW50cmEgYSBmYXRoZXJtb3Rvc3BvcnQuY29tLCBkZWphIHR1IGNvcnJlbyBlbiBsYSB2ZW50YW5hIGRlIGJpZW52ZW5pZGEgeSBkZXNjdWJyZSB0dSBjdXDDs24gZGUgMTAgJSBhIDI1ICUuIFNpIHlhIGxvIHRpZW5lcyB5IG5vIGxvIHVzYXN0ZSwgZXMgZWwgbWlzbW8uIFVuIGN1cMOzbiBwb3IgcGVyc29uYSB5IHVuIGRlc2N1ZW50byBwb3IgY29tcHJhLjwvcD48cD5HcmFjaWFzIHBvciByb2RhciBjb24gbm9zb3Ryb3MgZXN0ZSBhw7FvLjwvcD4iLCAiYm90b25fdGV4dG8iOiAiQ29tcHJhciBhbnRlcyBkZSBsYXMgMjM6NTkiLCAiYm90b25fdXJsIjogImh0dHBzOi8vd3d3LmZhdGhlcm1vdG9zcG9ydC5jb20vY2F0YWxvZz91dG1fc291cmNlPWVtYWlsJnV0bV9tZWRpdW09cGFuZWwmdXRtX2NhbXBhaWduPWJsYWNrLXdlZWstMjAyNiZ1dG1fY29udGVudD1kNS1ib3RvbiIsICJpbWFnZW5fYWx0IjogIlRvZG8gRmF0aGVyTW90b1Nwb3J0IMK3IEZhdGhlck1vdG9TcG9ydCBCbGFjayBXZWVrIn19" $PHP artisan tinker --execute='
$todos = json_decode(base64_decode(getenv("DATOS")), true);
$tz = "America/La_Paz";
$s = app(App\Services\CampanaManual::class);
$alas = App\Models\Product::where("slug", "alas-led-decorativo-iluminado")->first();
$precio = (float) $alas->precioFinal()["final_price"];
if (abs($precio - 84.99) > 0.01) { echo "ABORTO: las alas LED ya no cuestan 84.99 (ahora ".$precio.")".PHP_EOL; return; }
foreach ($todos as $n => $c) {
  $m = App\Models\MailCampaign::where("dedupe_key", "manual:black-week-2026-d".$n)->firstOrFail();
  if (! in_array($m->status, ["borrador", "programada"], true)) { echo "Día ".$n." está ".$m->status.": no se toca".PHP_EOL; continue; }
  $p = $m->payload; $p["contenido"]["es"] = $c;
  $m->update(["payload" => App\Services\CampanaManual::limpiarPayload($p)]);
  if ($m->status === "borrador") { $m = $s->programar($m->fresh()); }
  echo "Día ".$n." · #".$m->id." · ".$m->status." · ".$m->scheduled_at->timezone($tz)->format("Y-m-d H:i")." · ".$c["asunto"].PHP_EOL;
  Illuminate\Support\Facades\Mail::to(config("mail.ventas.address"))->send($s->correoDePrueba($m->fresh()));
}
echo "Pruebas enviadas a ".config("mail.ventas.address").PHP_EOL;
'

#!/bin/bash
# Solo lectura: pedidos pagados que todavía no se enviaron (lo mismo que el panel con
# filtro Pago = Pagado y Envío = Pendiente / Preparando), más un resumen de los en tránsito.
# No imprime teléfonos, emails ni direcciones (el log de GitHub Actions queda guardado).
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
$PHP artisan tinker --execute='
$hoy = now("America/La_Paz");
$pend = App\Models\Order::with(["items.variant.product","address","shipment"])
  ->where("payment_status","paid")->whereIn("shipping_status",["pending","preparing"])
  ->where("status","!=","cancelled")->orderBy("created_at")->get();
echo "=== POR ENVIAR (pagados): ".$pend->count()." ===".PHP_EOL;
foreach ($pend as $o) {
  $nombre = strtok(trim($o->address?->full_name ?? "—"), " ");
  $dias = (int) $o->created_at->diffInDays($hoy);
  echo PHP_EOL."# ".$o->order_number." | ".$o->created_at->timezone("America/La_Paz")->format("d/m H:i")." (".$dias." d) | envío: ".$o->shipping_status." | ".$o->shipping_method_name." | ".($o->address?->city ?? "—").", ".$o->country." | cliente: ".$nombre." | pago: ".$o->payment_method." | total USD ".$o->total.PHP_EOL;
  foreach ($o->items as $i) {
    echo "   - ".($i->variant?->product?->name ?? "Producto")." · ".collect([$i->size, $i->visor_color ? "visor ".$i->visor_color : null])->filter()->implode(" · ")." · x".$i->quantity.PHP_EOL;
  }
  if ($o->notes) echo "   nota: ".str($o->notes)->limit(120).PHP_EOL;
}
$trans = App\Models\Order::with("shipment")->where("shipping_status","in_transit")->where("status","!=","cancelled")->orderBy("created_at")->get();
echo PHP_EOL."=== EN TRÁNSITO: ".$trans->count()." ===".PHP_EOL;
foreach ($trans as $o) echo $o->order_number." | ".$o->country." | enviado ".($o->shipment?->shipped_at?->format("d/m") ?? "—")." | guía: ".($o->shipment?->tracking_number ? "sí" : "SIN GUÍA").PHP_EOL;
$imp = App\Models\Order::where("payment_status","pending")->where("status","!=","cancelled")->where("created_at",">=",now()->subDays(7))->count();
echo PHP_EOL."Pedidos sin pagar de los últimos 7 días (no se envían): ".$imp.PHP_EOL;
'

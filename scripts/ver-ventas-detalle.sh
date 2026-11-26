#!/bin/bash
# Solo lectura: detalle completo de pedidos puntuales (para el reporte a Gerson).
# Uso en el servidor: bash ver-ventas-detalle.sh  (pedidos fijos abajo).
# Imprime datos del cliente: borrar el log de la corrida después de leerlo.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || { echo "No existe $APP"; exit 1; }
$PHP artisan tinker --execute='
foreach (["FMS-0045","FMS-0065"] as $num) {
  $o = App\Models\Order::with(["items.variant.product","address","payments","shipment","coupons"])->where("order_number",$num)->first();
  if (! $o) { echo "NO EXISTE $num".PHP_EOL; continue; }
  $a = $o->address;
  echo PHP_EOL."===== ".$o->order_number." =====".PHP_EOL;
  echo "Creado: ".$o->created_at->timezone("America/La_Paz")->format("d/m/Y H:i")." (BO) | idioma: ".$o->locale.PHP_EOL;
  echo "Estado: ".$o->status." | pago: ".$o->payment_status." (".$o->payment_method.") | envío: ".$o->shipping_status." · ".$o->shipping_method_name.PHP_EOL;
  echo "Subtotal ".$o->subtotal." | descuento ".$o->discount." | envío ".$o->shipping." | TOTAL USD ".$o->total.PHP_EOL;
  try { foreach ($o->coupons as $c) echo "Cupón: ".($c->code ?? $c->coupon?->code ?? json_encode($c->only(["coupon_id","discount"]))).PHP_EOL; } catch (\Throwable $e) {}
  echo "Cliente: ".($a?->full_name)." | email: ".($o->user?->email ?? $o->guest_email ?? $o->notification_email)." | tel: ".($a?->phone).($o->user_id ? " | con cuenta" : " | invitado").PHP_EOL;
  echo "Dirección: ".collect([$a?->address_line, $a?->reference ? "(".$a->reference.")" : null, $a?->city, $a?->state, $a?->postal_code, $a?->country])->filter()->implode(", ").PHP_EOL;
  foreach ($o->items as $i) echo "  - ".($i->variant?->product?->name)." | SKU ".($i->variant?->sku)." | talla ".($i->size ?? "—")." | color ".($i->color ?? "—")." | visor ".($i->visor_color ?? "—")." | x".$i->quantity." | USD ".$i->unit_price." c/u | stock que queda: ".($i->variant?->stock).PHP_EOL;
  foreach ($o->payments as $p) echo "Pago: ".$p->provider." | ".$p->status." | ".$p->currency." ".$p->amount." | ".($p->paid_at?->timezone("America/La_Paz")->format("d/m/Y H:i") ?? "—")." | id ".$p->transaction_id.PHP_EOL;
  if ($o->upsell_of_order_id) echo "Es upsell de otro pedido".PHP_EOL;
  $ups = App\Models\Order::where("upsell_of_order_id",$o->id)->get(["order_number","total","payment_status"]);
  foreach ($ups as $u) echo "Upsell ligado: ".$u->order_number." USD ".$u->total." (".$u->payment_status.")".PHP_EOL;
  echo "Upsell ofrecido: ".($o->upsell_offered_at ? "sí" : "no").($o->upsell_dismissed_at ? " (rechazado)" : "").PHP_EOL;
  if ($o->notes) echo "Notas: ".$o->notes.PHP_EOL;
  if ($o->attention_reason) echo "Atención: ".$o->attention_reason.PHP_EOL;
  $n = App\Models\Order::where(fn($q)=>$q->where("guest_email",$o->guest_email ?? "-")->orWhere("user_id",$o->user_id ?? "-"))->where("payment_status","paid")->count();
  echo "Compras pagadas de este cliente: ".$n.PHP_EOL;
}
'

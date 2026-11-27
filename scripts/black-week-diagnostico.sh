#!/bin/bash
# Black Week 2026 · diagnóstico SOLO LECTURA antes de cargar las campañas de correo.
# No crea, cambia ni envía nada. No imprime correos ni datos personales.
set -u
APP="/home/u206357267/domains/fathermotosport.com/api"
PHP="/opt/alt/php82/usr/bin/php"
cd "$APP" || exit 1
$PHP artisan tinker --execute='
echo "== Hora del servidor (La Paz): ".now("America/La_Paz")->format("Y-m-d H:i").PHP_EOL;
echo "== Remitente: ".config("mail.from.name")." <".config("mail.from.address").">  · mailer: ".config("mail.default").PHP_EOL;
echo "== Cupo promocional: ".json_encode(config("marketing.limits")).PHP_EOL;
echo "== Destinatarios de promociones (correo comprobado y sin baja): ".App\Models\User::destinatariosDePromociones()->count().PHP_EOL;
echo "== Enviados hoy: ".app(App\Services\EnvioPromocional::class)->enviadosHoy().PHP_EOL;
echo "== Cupones activos:".PHP_EOL;
foreach (App\Models\Coupon::where("is_active", true)->whereNull("user_id")->orderBy("code")->get() as $c) {
  echo "   ".$c->code." · ".$c->type." ".$c->value." · min ".($c->minimum_amount ?? 0)." · usos ".$c->used_count."/".($c->max_uses ?? "∞")." · hasta ".($c->end_date ?? $c->expires_at ?? "—").PHP_EOL;
}
echo "== Cupones BLACK20 / CASCO15: ".App\Models\Coupon::whereIn("code",["BLACK20","CASCO15"])->pluck("code")->implode(", ").PHP_EOL;
echo "== Descuentos por categoría activos:".PHP_EOL;
foreach (App\Models\Category::where("discount_active", true)->orderBy("name")->get() as $k) {
  echo "   ".$k->name." · ".$k->discount_percent."% · ".optional($k->discount_starts_at)->format("Y-m-d H:i")." → ".optional($k->discount_ends_at)->format("Y-m-d H:i").PHP_EOL;
}
echo "== Campañas manuales recientes:".PHP_EOL;
foreach (App\Models\MailCampaign::where("type", "manual")->latest()->take(8)->get() as $m) {
  echo "   #".$m->id." ".$m->name." · ".$m->status." · ".optional($m->scheduled_at)->timezone("America/La_Paz")?->format("Y-m-d H:i")." · enviados ".$m->sent_count."/".$m->total_count.PHP_EOL;
}
'

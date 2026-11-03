<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remarketing, fase 4: campañas manuales y webhook de Resend. Reutiliza mail_campaigns y
 * mail_campaign_recipients de la fase 3; solo suma lo que falta:
 *
 * - mail_campaigns.scheduled_at: cuándo sale una campaña programada (UTC). El comando
 *   campanas:enviar la pasa a "enviando" al llegar la hora.
 * - mail_campaign_recipients.bounced_at / complained_at: rebote duro o queja de ese correo,
 *   que avisa el webhook de Resend (para el historial de la campaña).
 * - resend_webhook_events: los svix-id ya procesados, para no aplicar dos veces el mismo
 *   evento. Solo el id y el tipo: ningún correo ni contenido del evento.
 *
 * Aditiva y se puede volver a correr: cada columna y tabla se crea solo si falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->existe(fn () => Schema::hasColumn('mail_campaigns', 'scheduled_at'))) {
            Schema::table('mail_campaigns', function (Blueprint $t) {
                $t->timestamp('scheduled_at')->nullable()->after('status');
                $t->index(['status', 'scheduled_at']);
            });
        }

        foreach (['bounced_at', 'complained_at'] as $columna) {
            if (! $this->existe(fn () => Schema::hasColumn('mail_campaign_recipients', $columna))) {
                Schema::table('mail_campaign_recipients', fn (Blueprint $t) => $t->timestamp($columna)->nullable()->after('sent_at'));
            }
        }

        if (! $this->existe(fn () => Schema::hasTable('resend_webhook_events'))) {
            Schema::create('resend_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('svix_id', 100)->unique();
                $table->string('type', 50);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resend_webhook_events');

        $columnas = array_values(array_filter(['bounced_at', 'complained_at'], fn (string $c) => Schema::hasColumn('mail_campaign_recipients', $c)));
        if ($columnas !== []) {
            Schema::table('mail_campaign_recipients', fn (Blueprint $t) => $t->dropColumn($columnas));
        }

        if (Schema::hasColumn('mail_campaigns', 'scheduled_at')) {
            Schema::table('mail_campaigns', function (Blueprint $t) {
                $t->dropIndex(['status', 'scheduled_at']);
                $t->dropColumn('scheduled_at');
            });
        }
    }

    /** Consulta real también con --pretend, para que muestre solo lo que va a correr. */
    private function existe(Closure $consulta): bool
    {
        return Schema::getConnection()->withoutPretending($consulta);
    }
};

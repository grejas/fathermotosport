<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remarketing, fase 3: aviso por correo al activar un descuento de categoría. Las tablas
 * son genéricas para que las campañas manuales (fase 4) las reutilicen.
 *
 * - categories.notify_customers: interruptor "Avisar a los clientes por correo", apagado.
 *   Encenderlo no envía nada: solo habilita la acción "Avisar por correo".
 * - mail_campaigns: un envío (aviso de descuento o campaña), con dedupe_key única para no
 *   repetir el mismo aviso, estado y contadores.
 * - mail_campaign_recipients: un destinatario por fila (clave única campaña + usuario),
 *   con estado, intentos y motivo; el comando de tandas los toma de a N.
 *
 * Aditiva y se puede volver a correr: cada columna y tabla se crea solo si falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->existe(fn () => Schema::hasColumn('categories', 'notify_customers'))) {
            Schema::table('categories', fn (Blueprint $t) => $t->boolean('notify_customers')->default(false)->after('discount_ends_at'));
        }

        if (! $this->existe(fn () => Schema::hasTable('mail_campaigns'))) {
            Schema::create('mail_campaigns', function (Blueprint $table) {
                $table->id();
                // category_discount (fase 3) o manual (fase 4).
                $table->string('type', 30);
                $table->string('name');
                // Mismo aviso = misma clave: category:{id}:{porcentaje}:{inicio}:{fin}.
                $table->string('dedupe_key', 191)->nullable()->unique();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                // Datos del correo congelados al crearlo (categoría, porcentaje, fechas...).
                $table->json('payload')->nullable();
                // enviando, completada o cancelada (la fase 4 sumará borrador y programada).
                $table->string('status', 20)->default('enviando');
                $table->string('cancel_reason', 255)->nullable();
                $table->uuid('created_by')->nullable();
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('pending_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'id']);
            });
        }

        if (! $this->existe(fn () => Schema::hasTable('mail_campaign_recipients'))) {
            Schema::create('mail_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_campaign_id')->constrained('mail_campaigns')->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                $table->string('locale', 5)->nullable();
                // pendiente, enviando, enviado, fallido, omitido o cancelado.
                $table->string('status', 20)->default('pendiente');
                $table->unsignedTinyInteger('attempts')->default(0);
                // Motivo de omisión, error o cancelación (sin datos del proveedor).
                $table->string('reason', 255)->nullable();
                // Marca de la tanda que lo tomó (actualización atómica).
                $table->uuid('claim_token')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['mail_campaign_id', 'user_id']);
                $table->index(['mail_campaign_id', 'status']);
                $table->index('claim_token');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_campaign_recipients');
        Schema::dropIfExists('mail_campaigns');

        if (Schema::hasColumn('categories', 'notify_customers')) {
            Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('notify_customers'));
        }
    }

    /** Consulta real también con --pretend, para que muestre solo lo que va a correr. */
    private function existe(Closure $consulta): bool
    {
        return Schema::getConnection()->withoutPretending($consulta);
    }
};

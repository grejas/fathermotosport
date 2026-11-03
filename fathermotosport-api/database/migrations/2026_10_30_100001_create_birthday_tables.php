<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remarketing, fase 2: correos de cumpleaños.
 *
 * - birthday_settings: fila única con el porcentaje (5 %), los días de validez (7) y el
 *   interruptor de envío automático, APAGADO hasta que el administrador lo encienda.
 * - birthday_emails: un correo por cliente y por año (clave única user_id + year), con el
 *   cupón que se le creó, para no duplicar aunque el comando se repita o falle a medias.
 *
 * Aditiva y se puede volver a correr: cada tabla se crea solo si falta y la fila de
 * ajustes solo se inserta si no hay ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->existe('birthday_settings')) {
            Schema::create('birthday_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('discount_percent', 5, 2)->default(5);
                $table->unsignedSmallInteger('valid_days')->default(7);
                $table->boolean('auto_send')->default(false);
                $table->timestamps();
            });
        }

        if (! $this->existe('birthday_emails')) {
            Schema::create('birthday_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                // Día del calendario en que se le saluda (el 28/2 para quien nació el 29/2 en
                // un año no bisiesto).
                $table->date('birthday_date');
                $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
                // pendiente, enviado, fallido u omitido.
                $table->string('status', 20)->default('pendiente');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('last_error', 255)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'year']);
                $table->index(['status', 'year']);
            });
        }

        // La fila de ajustes es una escritura: con --pretend no se hace (no hay tabla todavía).
        if (! Schema::getConnection()->pretending() && DB::table('birthday_settings')->doesntExist()) {
            DB::table('birthday_settings')->insert([
                'discount_percent' => 5,
                'valid_days' => 7,
                'auto_send' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_emails');
        Schema::dropIfExists('birthday_settings');
    }

    /** Consulta real también con --pretend, para que muestre solo lo que va a correr. */
    private function existe(string $tabla): bool
    {
        return Schema::getConnection()->withoutPretending(fn () => Schema::hasTable($tabla));
    }
};

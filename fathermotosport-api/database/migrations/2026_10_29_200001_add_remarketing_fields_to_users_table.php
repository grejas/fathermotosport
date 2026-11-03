<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remarketing, fase 1: idioma del cliente, declaración de mayoría de edad, de dónde sale
 * la verificación del correo, baja de promociones y los campos de rebote y queja que
 * llenará el webhook de Resend (fase 4). Más la tabla con el registro de cada baja.
 *
 * Aditiva: ninguna cuenta cambia de estado ni pierde datos. Se puede volver a correr:
 * cada columna y la tabla se crean solo si faltan.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'locale',
        'adult_confirmation',
        'adult_confirmed_at',
        'email_verified_via',
        'email_verified_by',
        'profile_prompted_at',
        'promo_unsubscribed_at',
        'email_bounced_at',
        'email_complained_at',
    ];

    public function up(): void
    {
        $definiciones = [
            // es, pt o en: el idioma en que se le escribe.
            'locale' => fn (Blueprint $t) => $t->string('locale', 5)->nullable()->after('country'),
            // birth_date = lo prueba su fecha de nacimiento; declared = marcó la casilla.
            'adult_confirmation' => fn (Blueprint $t) => $t->string('adult_confirmation', 20)->nullable()->after('birth_date'),
            'adult_confirmed_at' => fn (Blueprint $t) => $t->timestamp('adult_confirmed_at')->nullable()->after('adult_confirmation'),
            // code, google, paypal, password_reset o admin.
            'email_verified_via' => fn (Blueprint $t) => $t->string('email_verified_via', 20)->nullable()->after('email_verified_at'),
            // Administrador que lo marcó a mano (sin clave foránea: es solo un registro).
            'email_verified_by' => fn (Blueprint $t) => $t->uuid('email_verified_by')->nullable()->after('email_verified_via'),
            // Cuándo se le mostró la ventana "Completa tus datos" (sale una sola vez).
            'profile_prompted_at' => fn (Blueprint $t) => $t->timestamp('profile_prompted_at')->nullable(),
            'promo_unsubscribed_at' => fn (Blueprint $t) => $t->timestamp('promo_unsubscribed_at')->nullable(),
            'email_bounced_at' => fn (Blueprint $t) => $t->timestamp('email_bounced_at')->nullable(),
            'email_complained_at' => fn (Blueprint $t) => $t->timestamp('email_complained_at')->nullable(),
        ];

        foreach ($definiciones as $columna => $definir) {
            if (! $this->existe(fn () => Schema::hasColumn('users', $columna))) {
                Schema::table('users', $definir);
            }
        }

        if (! $this->existe(fn () => Schema::hasTable('mail_unsubscribes'))) {
            Schema::create('mail_unsubscribes', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                // link (página de baja), one_click (List-Unsubscribe-Post) o admin.
                $table->string('source', 20);
                // Qué correo la originó (ej. campaign:12, birthday:2027), si se sabe.
                $table->string('reference', 100)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('email');
            });
        }
    }

    /** Consulta real también con --pretend, para que muestre solo lo que va a correr. */
    private function existe(Closure $consulta): bool
    {
        return Schema::getConnection()->withoutPretending($consulta);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_unsubscribes');

        $columnas = array_values(array_filter(self::COLUMNAS, fn (string $c) => Schema::hasColumn('users', $c)));
        if ($columnas !== []) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn($columnas));
        }
    }
};

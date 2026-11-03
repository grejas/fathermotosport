<?php

namespace App\Models;

use App\Mail\ResetPasswordMail;
use App\Models\Concerns\HasUuid;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    /**
     * Orígenes de email_verified_via que prueban que el correo es del cliente: código por
     * correo, Google, PayPal o un administrador. password_reset no está en la lista.
     */
    public const VERIFICACION_CON_EVIDENCIA = ['code', 'google', 'paypal', 'admin'];

    /**
     * Solo Administrador y Empleado pueden acceder al panel de administración.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && ($this->isAdmin() || $this->isEmpleado());
    }

    /**
     * Nombre mostrado en el panel de Filament (no existe columna `name`).
     */
    public function getFilamentName(): string
    {
        return $this->full_name ?: $this->email;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar;
    }

    /**
     * Indica si corresponde mostrar el recordatorio de seguridad (cada 14 días,
     * no bloqueante). Se basa en el último cambio de contraseña (o la fecha de
     * registro si nunca cambió) y en cuándo se descartó el recordatorio.
     */
    public function needsSecurityReminder(): bool
    {
        $reference = $this->last_password_change ?? $this->created_at;

        // Cuenta demasiado nueva o cambio de contraseña reciente → sin recordatorio.
        if (! $reference || $reference->greaterThan(now()->subDays(14))) {
            return false;
        }

        // Descartado hace menos de 14 días → seguir oculto.
        if ($this->security_reminder_dismissed_at
            && $this->security_reminder_dismissed_at->greaterThan(now()->subDays(14))) {
            return false;
        }

        return true;
    }

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'birth_date',
        'password',
        'avatar',
        'status',
        'email_verified_at',
        'loyalty_discount_used',
        'last_password_change',
        'security_reminder_dismissed_at',
        'locale',
        'adult_confirmation',
        'adult_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'loyalty_discount_used' => 'boolean',
            'last_password_change' => 'datetime',
            'security_reminder_dismissed_at' => 'datetime',
            'adult_confirmed_at' => 'datetime',
            'profile_prompted_at' => 'datetime',
            'promo_unsubscribed_at' => 'datetime',
            'email_bounced_at' => 'datetime',
            'email_complained_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // La base borra sus reseñas en cascada, sin pasar por el modelo: se borran antes
        // una por una para que se vayan también los archivos de sus fotos. Con la
        // papelera (soft delete) no: la cuenta se puede restaurar con sus reseñas.
        static::forceDeleting(function (User $user) {
            $user->reviews()->get()->each->delete();
        });
    }

    // Relaciones
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'favorites')->withTimestamps();
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * ÚNICO criterio de quién puede recibir correo promocional (campañas, cumpleaños,
     * avisos de descuento): cliente, cuenta activa y no borrada, correo verificado (por
     * código, Google, PayPal, el enlace de contraseña o un administrador), sin baja y sin
     * email_bounced_at ni email_complained_at (hoy ningún código los llena; se respetan si
     * alguien los marca a mano).
     *
     * El formato del correo y los duplicados se filtran en destinatariosDePromociones(),
     * porque no se pueden comprobar bien en SQL.
     */
    public function scopeRecibePromociones(Builder $query): Builder
    {
        return $query
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->where('status', 'active')
            ->conCorreoComprobado()
            ->whereNull('promo_unsubscribed_at')
            ->whereNull('email_bounced_at')
            ->whereNull('email_complained_at');
    }

    /**
     * Correo verificado CON evidencia de que existe y es del cliente. email_verified_at
     * solo no alcanza: hay cuentas antiguas o de prueba con el campo lleno y sin rastro de
     * cómo se verificó. Cuenta como evidencia cualquiera de:
     *
     * a) un código de email_verification_codes USADO para ese correo (los códigos nunca se
     *    borran; used_at también se llena al pedir un código nuevo, pero no se puede pedir
     *    uno para un correo ya verificado, así que en una cuenta verificada es su código);
     * b) Google (avatar de googleusercontent.com, en las cuentas anteriores a la fase 1);
     * c) email_verified_via en VERIFICACION_CON_EVIDENCIA (desde la fase 1).
     */
    public function scopeConCorreoComprobado(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at')->where(fn (Builder $q) => $q
            ->whereIn('email_verified_via', self::VERIFICACION_CON_EVIDENCIA)
            ->orWhere('avatar', 'like', '%googleusercontent.com%')
            ->orWhereExists(fn ($codigos) => $codigos->selectRaw('1')
                ->from('email_verification_codes')
                ->whereNotNull('email_verification_codes.used_at')
                ->whereRaw('lower(email_verification_codes.email) = lower(users.email)')));
    }

    /** Mismo criterio que scopeConCorreoComprobado, para una cuenta ya cargada. */
    public function tieneEvidenciaDeCorreo(): bool
    {
        return $this->email_verified_at !== null && (
            in_array($this->email_verified_via, self::VERIFICACION_CON_EVIDENCIA, true)
            || str_contains((string) $this->avatar, 'googleusercontent.com')
            || EmailVerificationCode::whereNotNull('used_at')
                ->whereRaw('lower(email) = ?', [mb_strtolower((string) $this->email)])
                ->exists()
        );
    }

    /**
     * Destinatarios de una promoción: el scope, más formato de correo válido y un solo
     * envío por dirección (sin distinguir mayúsculas; se queda la cuenta más antigua).
     *
     * @return Collection<int, self>
     */
    public static function destinatariosDePromociones(?Builder $consulta = null): Collection
    {
        return ($consulta ?? self::query())
            ->recibePromociones()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $u) => self::correoConFormatoValido($u->email))
            ->unique(fn (self $u) => mb_strtolower(trim($u->email)))
            ->values();
    }

    public static function correoConFormatoValido(?string $email): bool
    {
        return filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Por qué esta cuenta NO recibe promociones, para el historial ("omitido: correo no
     * verificado"). null = sí puede recibirlas. Mismo criterio que scopeRecibePromociones.
     */
    public function motivoSinPromociones(): ?string
    {
        return match (true) {
            ! $this->isCliente() => 'no es cliente',
            $this->trashed() => 'cuenta borrada',
            $this->status !== 'active' => 'cuenta inactiva',
            $this->email_verified_at === null => 'correo no verificado',
            ! $this->tieneEvidenciaDeCorreo() => 'correo sin evidencia de verificación',
            ! self::correoConFormatoValido($this->email) => 'correo con formato inválido',
            $this->promo_unsubscribed_at !== null => 'dado de baja',
            $this->email_bounced_at !== null => 'rebote',
            $this->email_complained_at !== null => 'queja de spam',
            default => null,
        };
    }

    /**
     * Marca el correo como verificado y guarda de dónde sale (code, google, paypal,
     * password_reset, admin) y, si fue a mano, qué administrador lo hizo.
     */
    public function marcarCorreoVerificado(string $via, ?self $admin = null): void
    {
        $this->forceFill([
            'email_verified_at' => now(),
            'email_verified_via' => $via,
            'email_verified_by' => $admin?->id,
        ])->save();
    }

    /** Le faltan teléfono o país: se le piden una vez, sin bloquear nada. */
    public function perfilIncompleto(): bool
    {
        return blank($this->phone) || blank($this->country);
    }

    /** Ventana "Completa tus datos": cliente con algo pendiente al que aún no se le mostró. */
    public function debeVerVentanaDePerfil(): bool
    {
        return $this->isCliente()
            && $this->profile_prompted_at === null
            && ($this->perfilIncompleto() || $this->email_verified_at === null);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Helpers
    public function isAdmin(): bool
    {
        return optional($this->role)->slug === 'administrador';
    }

    public function isEmpleado(): bool
    {
        return optional($this->role)->slug === 'empleado';
    }

    public function isCliente(): bool
    {
        return optional($this->role)->slug === 'cliente';
    }

    /**
     * Cuenta de cliente cuyo email nadie comprobó: la creada desde un pedido pagado con
     * tarjeta, donde el email lo escribió quien compró. El dueño real de ese email puede
     * reclamarla verificando el código por correo. El personal nunca entra acá.
     */
    public static function reclamablePorEmail(string $email): ?self
    {
        return self::where('email', $email)
            ->whereNull('email_verified_at')
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->first();
    }

    /**
     * "Email ya registrado" para el registro normal: toda cuenta salvo las reclamables
     * (las borradas también cuentan, porque el email sigue ocupado en la tabla).
     */
    public static function reglaEmailOcupado(): Unique
    {
        return Rule::unique('users', 'email')->where(fn (QueryBuilder $q) => $q
            ->whereNotNull('email_verified_at')
            ->orWhereNotNull('deleted_at')
            ->orWhereNotIn('role_id', Role::where('slug', 'cliente')->pluck('id')->all()));
    }

    /** Personal del panel: Administrador o Empleado. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isEmpleado();
    }

    /**
     * Envía el email de recuperación con el diseño Dark Race, apuntando al
     * frontend Next.js. El token expira en 60 minutos (config/auth.php).
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            .'/reset-password?token='.$token
            .'&email='.urlencode($this->email);

        Mail::to($this->email)->send(new ResetPasswordMail($this, $resetUrl));
    }
}

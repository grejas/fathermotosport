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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

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
        ];
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
            . '/reset-password?token=' . $token
            . '&email=' . urlencode($this->email);

        Mail::to($this->email)->send(new ResetPasswordMail($this, $resetUrl));
    }
}

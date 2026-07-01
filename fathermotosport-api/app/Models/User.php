<?php

namespace App\Models;

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

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'email_verified_at',
        'loyalty_discount_used',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'loyalty_discount_used' => 'boolean',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreConfig extends Model
{
    protected $table = 'store_config';

    protected $fillable = [
        'store_name',
        'logo_url',
        'favicon_url',
        'phone',
        'email',
        'currency',
        'whatsapp',
        'facebook',
        'instagram',
        'youtube',
        'maintenance_mode',
        'payment_keys',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'payment_keys' => 'array',
    ];

    /**
     * Devuelve la configuración única de la tienda (singleton).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}

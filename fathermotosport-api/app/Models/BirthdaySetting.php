<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes del correo de cumpleaños (fila única): porcentaje del cupón, días de validez e
 * interruptor de envío automático (apagado por defecto). Se editan en el panel.
 */
class BirthdaySetting extends Model
{
    public const PORCENTAJE_MIN = 0.5;

    public const PORCENTAJE_MAX = 90;

    public const DIAS_MIN = 1;

    public const DIAS_MAX = 90;

    protected $fillable = ['discount_percent', 'valid_days', 'auto_send'];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'valid_days' => 'integer',
        'auto_send' => 'boolean',
    ];

    /** La fila única; si no existe (base sin migrar la fila), con los valores iniciales. */
    public static function actual(): self
    {
        return static::query()->first()
            ?? static::query()->create(['discount_percent' => 5, 'valid_days' => 7, 'auto_send' => false]);
    }
}

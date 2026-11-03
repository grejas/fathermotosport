<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un correo de cumpleaños por cliente y por año (clave única user_id + year). Guarda el
 * cupón creado para ese año: si el envío falla o se pospone, se reintenta con el mismo.
 */
class BirthdayEmail extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public const OMITIDO = 'omitido';

    /** Intentos fallidos tras los que se deja de reintentar. */
    public const MAX_INTENTOS = 5;

    protected $fillable = ['user_id', 'year', 'birthday_date', 'coupon_id', 'status', 'attempts', 'last_error', 'sent_at'];

    protected $casts = [
        'year' => 'integer',
        'birthday_date' => 'date',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}

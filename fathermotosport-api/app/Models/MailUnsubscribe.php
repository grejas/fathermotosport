<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada baja de promociones: quién, desde dónde y por qué correo. */
class MailUnsubscribe extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'source', 'reference'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

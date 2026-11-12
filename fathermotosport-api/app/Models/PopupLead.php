<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contacto captado por el popup de la web y su cupón. */
class PopupLead extends Model
{
    protected $fillable = [
        'email',
        'whatsapp',
        'country',
        'locale',
        'coupon_id',
        'percent',
        'source',
        'consent',
        'consent_at',
        'ip_hash',
    ];

    protected $casts = [
        'source' => 'array',
        'consent' => 'boolean',
        'consent_at' => 'datetime',
        'percent' => 'integer',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}

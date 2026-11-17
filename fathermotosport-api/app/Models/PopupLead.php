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
        'welcome_sent_at',
        'reminder_sent_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'source' => 'array',
        'consent' => 'boolean',
        'consent_at' => 'datetime',
        'percent' => 'integer',
        'welcome_sent_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function idioma(): string
    {
        return in_array($this->locale, ['es', 'pt', 'en'], true) ? $this->locale : 'es';
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}

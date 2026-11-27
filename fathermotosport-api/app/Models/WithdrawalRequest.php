<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cliente que ejerció el derecho de desistimiento desde /withdrawal. */
class WithdrawalRequest extends Model
{
    protected $fillable = [
        'order_number',
        'order_id',
        'name',
        'email',
        'items',
        'reason',
        'locale',
        'ip_hash',
        'confirmation_sent_at',
        'handled_at',
    ];

    protected $casts = [
        'confirmation_sent_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Número visible para el cliente (D-000123). */
    public function referencia(): string
    {
        return 'D-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}

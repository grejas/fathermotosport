<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cliente que pidió que le avisen cuando un producto agotado vuelva a tener stock. */
class StockAlert extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'email',
        'whatsapp',
        'locale',
        'ip_hash',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

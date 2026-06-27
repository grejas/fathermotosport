<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuid;

    protected $fillable = [
        'order_number',
        'user_id',
        'address_id',
        'guest_email',
        'status',
        'subtotal',
        'discount',
        'shipping',
        'tax',
        'total',
        'payment_status',
        'shipping_status',
        'payment_method',
        'country',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Genera un correlativo tipo FMS-0001.
     */
    public static function generateOrderNumber(): string
    {
        $last = static::query()
            ->select('order_number')
            ->where('order_number', 'like', 'FMS-%')
            ->orderByDesc('order_number')
            ->lockForUpdate()
            ->value('order_number');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'FMS-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(OrderCoupon::class);
    }

    // Scopes
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }
}

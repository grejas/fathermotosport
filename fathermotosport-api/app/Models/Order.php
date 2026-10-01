<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Order extends Model
{
    /**
     * Marcador que llevan nombre y dirección de un pedido PayPal Express hasta que
     * el cliente vuelve de PayPal: recién ahí se conocen sus datos reales.
     * (addresses.full_name y .address_line no aceptan null.)
     */
    public const DATO_PENDIENTE = 'Pendiente — PayPal';

    /**
     * Métodos de pago que NO cobran en el acto y necesitan que una persona coordine el
     * cobro (transferencia, contra entrega, WhatsApp). Son los únicos que reciben el
     * correo de "recibimos tu pedido" al crearse.
     *
     * Hoy está vacía a propósito: paypal, stripe y mercadopago son pasarelas que cobran
     * en el momento, así que su primer correo es el de pago confirmado, desde markPaid().
     *
     * Es una lista de los que SÍ avisan y no de los que no, justamente para que una
     * pasarela nueva quede fuera del correo prematuro sin que nadie tenga que acordarse
     * de agregarla acá. Antes la condición era `payment_method !== 'paypal'` y por eso
     * Stripe avisaba "recibimos tu pedido" incluso con la tarjeta rechazada.
     */
    public const METODOS_CON_COORDINACION_MANUAL = [];

    use HasUuid;

    protected $fillable = [
        'order_number',
        'access_token',
        'user_id',
        'address_id',
        'guest_email',
        'email_verificado_por',
        'status',
        'subtotal',
        'discount',
        'shipping',
        'shipping_option_id',
        'shipping_method_name',
        'tax',
        'total',
        'payment_status',
        'shipping_status',
        'payment_method',
        'country',
        'notes',
        'attention_reason',
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

            // Token del comprador (sobre todo el invitado, que no tiene cuenta).
            if (empty($order->access_token)) {
                $order->access_token = Str::random(48);
            }
        });
    }

    /**
     * ¿Quien pide puede ver/pagar este pedido? Vale por token de acceso (comprador
     * invitado o enlace del email) o por sesión: el dueño del pedido y el staff.
     */
    public function isAccessibleBy(?User $user, ?string $token): bool
    {
        if (filled($token) && filled($this->access_token) && hash_equals($this->access_token, $token)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->isEmpleado() || ($this->user_id !== null && $this->user_id === $user->id);
    }

    /** Token de acceso que llega por query (?token=) o por la cabecera X-Order-Token. */
    public static function tokenFromRequest(Request $request): ?string
    {
        return $request->header('X-Order-Token') ?? $request->query('token');
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

    /**
     * ¿Es un pedido Express que todavía espera los datos del comprador?
     * Si lo es, PayPal debe pedir la dirección (shipping_preference GET_FROM_FILE)
     * y al volver hay que completar el pedido con lo que informó.
     */
    public function esperaDatosDePaypal(): bool
    {
        return $this->address?->address_line === self::DATO_PENDIENTE;
    }

    /**
     * ¿El cobro de este pedido lo coordina una persona, en vez de una pasarela?
     * Decide si al crearse se le avisa al cliente que recibimos su pedido.
     */
    public function requiereCoordinacionManual(): bool
    {
        return in_array($this->payment_method, self::METODOS_CON_COORDINACION_MANUAL, true);
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

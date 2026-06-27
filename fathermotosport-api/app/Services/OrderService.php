<?php

namespace App\Services;

use App\Models\Address;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private CouponService $coupons)
    {
    }

    /**
     * Crea un pedido completo de forma transaccional:
     * valida stock, calcula totales, aplica cupón, descuenta stock y registra movimientos.
     *
     * @throws ValidationException
     */
    public function createOrder(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $user) {
            // 1. Cargar y bloquear las variantes implicadas (evita sobreventa concurrente).
            $variantIds = collect($data['items'])->pluck('variant_id')->all();
            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            // 2. Validar stock y armar las líneas del pedido.
            foreach ($data['items'] as $line) {
                /** @var ProductVariant|null $variant */
                $variant = $variants->get($line['variant_id']);
                $qty = (int) $line['quantity'];

                if (! $variant || ! $variant->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["La variante {$line['variant_id']} no está disponible."],
                    ]);
                }

                if ($variant->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para {$variant->sku}. Disponible: {$variant->stock}."],
                    ]);
                }

                $unitPrice = $this->resolveUnitPrice($variant);
                $lineSubtotal = round($unitPrice * $qty, 2);
                $subtotal += $lineSubtotal;

                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // 3. Crear la dirección del pedido.
            $address = Address::create(array_merge($data['address'], [
                'user_id' => $user?->id,
            ]));

            // 4. Calcular descuento por cupón (si aplica).
            $discount = 0;
            $validCoupon = null;
            if (! empty($data['coupon_code'])) {
                $validation = $this->coupons->validateCoupon($data['coupon_code'], $subtotal);
                if (! $validation['valid']) {
                    throw ValidationException::withMessages([
                        'coupon_code' => [$validation['message']],
                    ]);
                }
                $discount = $validation['discount'];
                $validCoupon = $validation['coupon'];
            }

            // 5. Crear el pedido. Envío y tax siempre 0.00 (envío gratis).
            $total = max(0, round($subtotal - $discount, 2));

            $order = Order::create([
                'user_id' => $user?->id,
                'address_id' => $address->id,
                'guest_email' => $user ? null : ($data['guest_email'] ?? null),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => 0.00,
                'tax' => 0.00,
                'total' => $total,
                'payment_status' => 'pending',
                'shipping_status' => 'pending',
                'payment_method' => $data['payment_method'],
                'country' => $address->country,
                'notes' => $data['notes'] ?? null,
            ]);

            // 6. Crear items, descontar stock y registrar movimientos de inventario.
            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                    'size' => $variant->size,
                    'color' => $variant->color,
                ]);

                $variant->decrement('stock', $line['quantity']);

                InventoryMovement::create([
                    'product_id' => $variant->product_id,
                    'user_id' => $user?->id,
                    'type' => 'sale',
                    'quantity' => -$line['quantity'],
                    'reason' => "Venta - pedido {$order->order_number}",
                ]);
            }

            // 7. Registrar y consumir el cupón.
            if ($validCoupon) {
                $this->coupons->applyCoupon($data['coupon_code'], $order->id, $subtotal, $user);
            }

            return $order->load(['items.variant.product', 'address']);
        });
    }

    /**
     * Precio unitario: precio propio de la variante, o el precio vigente del producto.
     */
    private function resolveUnitPrice(ProductVariant $variant): float
    {
        if (! is_null($variant->price)) {
            return (float) $variant->price;
        }

        $product = $variant->product;

        return (float) ($product->sale_price ?? $product->price);
    }
}

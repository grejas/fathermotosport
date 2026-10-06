<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private CouponService $coupons,
        private ShippingWeightService $weights,
    ) {
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

                // También el producto: desactivarlo en el panel tiene que sacarlo de la
                // venta aunque sus tallas sigan marcadas como activas.
                if (! $variant || ! $variant->is_active || ! $variant->product?->is_active) {
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

            // 5. Resolver el envío elegido. El precio SIEMPRE sale de la base: nunca se
            // confía en lo que manda el navegador. Sin opción elegida, envío 0 (se coordina aparte).
            $shippingOption = $this->resolveShippingOption($data, $lines);
            $shipping = $shippingOption ? (float) $shippingOption->price : 0.00;

            // 6. Crear el pedido. El total incluye el envío; tax sigue en 0.00.
            $total = max(0, round($subtotal - $discount, 2)) + $shipping;

            $order = Order::create([
                'user_id' => $user?->id,
                'address_id' => $address->id,
                'guest_email' => $user ? null : ($data['guest_email'] ?? null),
                // Solo lo trae PayPal Express; en el checkout normal el escrito ya es
                // guest_email (o el de la cuenta).
                'notification_email' => $data['notification_email'] ?? null,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => $shipping,
                'shipping_option_id' => $shippingOption?->id,
                'shipping_method_name' => $shippingOption?->method_name,
                'tax' => 0.00,
                'total' => round($total, 2),
                'payment_status' => 'pending',
                'shipping_status' => 'pending',
                'payment_method' => $data['payment_method'],
                'country' => $address->country,
                'locale' => Order::idiomaValido($data['locale'] ?? null),
                'notes' => $data['notes'] ?? null,
            ]);

            // 7. Crear los items del pedido.
            //
            // El stock NO se descuenta acá: se descuenta cuando el pago se confirma
            // (PaymentController::markPaid). Antes se reservaba al crear el pedido, y
            // cada cliente que abandonaba el pago dejaba unidades bloqueadas.
            // La validación de stock del paso 2 sigue siendo una comprobación previa.
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
                    // El color es del producto (un producto = un color).
                    'color' => $variant->product->color,
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
     * Valida la opción de envío elegida contra la base: debe seguir activa y cubrir el
     * país del pedido y el peso real de los items. Devuelve null si no se eligió ninguna.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $lines
     *
     * @throws ValidationException
     */
    private function resolveShippingOption(array $data, array $lines): ?ShippingOption
    {
        if (empty($data['shipping_option_id'])) {
            return null;
        }

        $countryCode = $data['shipping_country_code'] ?? null;

        if (blank($countryCode)) {
            throw ValidationException::withMessages([
                'shipping_country_code' => ['Falta el país de destino para validar el envío elegido.'],
            ]);
        }

        $weight = $this->weights->forItems(array_map(fn (array $line) => [
            'variant_id' => $line['variant']->id,
            'quantity' => $line['quantity'],
        ], $lines));

        $option = ShippingOption::findAvailable((int) $data['shipping_option_id'], $countryCode, $weight);

        if (! $option) {
            throw ValidationException::withMessages([
                'shipping_option_id' => ['La opción de envío elegida ya no está disponible para ese destino y peso.'],
            ]);
        }

        return $option;
    }

    /**
     * Precio unitario: siempre el precio vigente del producto. El precio no varía por
     * talla; product_variants.price queda en la BD sin uso y se ignora.
     */
    private function resolveUnitPrice(ProductVariant $variant): float
    {
        $product = $variant->product;

        return (float) ($product->sale_price ?? $product->price);
    }
}

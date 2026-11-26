<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use App\Models\User;
use App\Models\VisorColor;
use App\Services\Payments\ManualPaymentService;
use App\Support\Paises;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private CouponService $coupons,
        private ShippingWeightService $weights,
        private PrecioFinal $precios,
        private ManualPaymentService $manualPayments,
    ) {}

    /**
     * Crea un pedido completo de forma transaccional:
     * valida stock, calcula totales, aplica cupón, descuenta stock y registra movimientos.
     *
     * @throws ValidationException
     */
    public function createOrder(array $data, ?User $user = null): Order
    {
        // Transferencia / cripto: la cotización se pide antes de abrir la transacción
        // (es una llamada HTTP, no debe tener filas bloqueadas). Si no hay cotización,
        // el pedido no se crea.
        $cotizacion = ManualPaymentService::esManual($data['payment_method'] ?? null)
            ? $this->manualPayments->cotizar($data['payment_method'])
            : null;

        return DB::transaction(function () use ($data, $user, $cotizacion) {
            // 1. Cargar y bloquear las variantes implicadas (evita sobreventa concurrente).
            $variantIds = collect($data['items'])->pluck('variant_id')->all();
            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];
            // Unidades pedidas por variante: el mismo casco puede venir en dos líneas
            // (una por color de visor) y el stock se compara contra la suma.
            $unidadesPorVariante = [];
            // Todas las categorías y exclusiones frescas: nada de lo leído antes en la
            // petición decide el precio que se cobra.
            PrecioFinal::olvidar();

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

                $unidadesPorVariante[$variant->id] = ($unidadesPorVariante[$variant->id] ?? 0) + $qty;

                if ($variant->stock < $unidadesPorVariante[$variant->id]) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para {$variant->sku}. Disponible: {$variant->stock}."],
                    ]);
                }

                $unitPrice = $this->resolveUnitPrice($variant);
                $lineSubtotal = round($unitPrice * $qty, 2);
                $subtotal += $lineSubtotal;

                $lines[] = [
                    'variant' => $variant,
                    'visor' => $this->resolveVisorColor($variant, $line['visor_color_id'] ?? null),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // 2b. Lo que vio el cliente tiene que ser lo que se cobra. Si la web manda el
            // subtotal que mostró y los precios cambiaron (venció un descuento, se editó
            // un precio), se rechaza para que la web refresque y muestre el total nuevo
            // antes de pagar. Sin el campo (PayPal Express, una web vieja) sigue como antes:
            // igual se cobra el precio recalculado acá.
            if (isset($data['expected_subtotal'])
                && abs(round($subtotal, 2) - round((float) $data['expected_subtotal'], 2)) >= 0.005) {
                throw ValidationException::withMessages([
                    'expected_subtotal' => ['Los precios cambiaron. Revisa el total antes de pagar.'],
                ]);
            }

            // 3. Crear la dirección del pedido.
            $address = Address::create(array_merge($data['address'], [
                'user_id' => $user?->id,
            ]));

            // 4. Calcular descuento por cupón (si aplica).
            $discount = 0;
            $validCoupon = null;
            if (! empty($data['coupon_code'])) {
                $validation = $this->coupons->validateCoupon($data['coupon_code'], $subtotal, $user, bloquear: true);
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
            ] + ($cotizacion
                ? ManualPaymentService::camposDelPedido($data['payment_method'], round($total, 2), $cotizacion)
                : []));

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
                    'visor_color_id' => $line['visor']?->id,
                    'visor_color' => $line['visor']?->name,
                ]);
            }

            // 7. Registrar y consumir el cupón.
            if ($validCoupon) {
                $this->coupons->applyCoupon($data['coupon_code'], $order->id, $subtotal, $user);
            }

            // 8. A la cuenta le faltaban teléfono o país (antigua o de Google): se toman
            // de la dirección de envío, sin preguntar nada más.
            if ($user && $user->perfilIncompleto()) {
                $this->completarPerfilDesdeDireccion($user, $address, $data['shipping_country_code'] ?? null);
            }

            return $order->load(['items.variant.product', 'address']);
        });
    }

    /**
     * Color de visor elegido para un casco. Vale si es uno de los visores activos del
     * producto; si el producto no tiene visores propios, cualquiera activo del catálogo
     * global (lo mismo que muestra la web). Sin elección, null.
     *
     * @throws ValidationException
     */
    private function resolveVisorColor(ProductVariant $variant, ?string $visorColorId): ?VisorColor
    {
        if (! $visorColorId) {
            return null;
        }

        $product = $variant->product;
        $propios = $product->visorColors()->where('visor_colors.is_active', true);

        $visor = (clone $propios)->exists()
            ? $propios->where('visor_colors.id', $visorColorId)->first()
            : VisorColor::active()->whereKey($visorColorId)->first();

        if (! $visor) {
            throw ValidationException::withMessages([
                'items' => ["El color de visor elegido no está disponible para {$product->name}."],
            ]);
        }

        return $visor;
    }

    /**
     * Valida la opción de envío elegida contra la base: debe seguir activa y cubrir el
     * país del pedido y el peso real de los items. Devuelve null si no se eligió ninguna.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $lines
     *
     * @throws ValidationException
     */
    /** Solo llena lo que falta; un teléfono o país que no se reconocen se dejan vacíos. */
    private function completarPerfilDesdeDireccion(User $user, Address $address, ?string $codigoPais): void
    {
        $iso = Paises::iso($codigoPais) ?? Paises::iso($address->country);
        $cambios = [];

        if (blank($user->country) && $iso) {
            $cambios['country'] = $iso;
        }
        if (blank($user->phone) && ($telefono = Paises::telefonoDesdeDireccion($address->phone, $iso))) {
            $cambios['phone'] = $telefono;
        }

        if ($cambios !== []) {
            $user->forceFill($cambios)->save();
        }
    }

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
     * Precio unitario: siempre el precio final del producto en este momento (el mejor
     * entre su precio propio y el descuento de su categoría, ver PrecioFinal). El precio
     * no varía por talla; product_variants.price queda en la BD sin uso y se ignora.
     */
    private function resolveUnitPrice(ProductVariant $variant): float
    {
        return $this->precios->precio($variant->product);
    }
}

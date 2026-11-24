<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * API de Conversiones de Meta (servidor): envía la compra cuando un pedido pasa a pagado.
 *
 * Por qué existe: el píxel del navegador solo registra la compra si el cliente ve la
 * pantalla de éxito con el pago ya confirmado (tarjeta o PayPal). Los pagos manuales
 * (Mercury, cripto, SEPA) se confirman después en el panel y nunca llegaban a Meta, así
 * que el evento Purchase quedaba "inactivo" y las campañas no podían optimizar por venta.
 *
 * - Usa el mismo event_id que el navegador (pay-{id}): si llegan los dos, Meta cuenta uno.
 * - Sin META_CAPI_TOKEN o sin píxel configurado no hace nada.
 * - Nunca lanza: un fallo de Meta no puede afectar al cobro ni al panel.
 */
class MetaConversions
{
    private const API_VERSION = 'v21.0';

    public static function enabled(): bool
    {
        return filled(config('services.meta.capi_token')) && self::pixelIds() !== [];
    }

    /** @return string[] */
    public static function pixelIds(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) config('services.meta.pixel_id'))),
            fn ($id) => preg_match('/^\d{10,20}$/', $id) === 1
        ));
    }

    public function sendPurchase(Order $order): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            $order->loadMissing(['items.variant.product', 'address', 'user']);
            $evento = $this->purchaseEvent($order);
            $ok = true;

            foreach (self::pixelIds() as $pixelId) {
                $payload = ['data' => [$evento]];
                if (filled(config('services.meta.test_event_code'))) {
                    $payload['test_event_code'] = config('services.meta.test_event_code');
                }

                $res = Http::timeout(8)
                    ->asJson()
                    ->post(
                        'https://graph.facebook.com/'.self::API_VERSION."/{$pixelId}/events?access_token="
                            .urlencode((string) config('services.meta.capi_token')),
                        $payload
                    );

                if (! $res->successful()) {
                    $ok = false;
                    Log::warning('Meta CAPI: compra rechazada', [
                        'order' => $order->order_number,
                        'pixel' => $pixelId,
                        'status' => $res->status(),
                        'error' => $res->json('error.message'),
                    ]);
                }
            }

            if ($ok) {
                Log::info('Meta CAPI: compra enviada', ['order' => $order->order_number]);
            }

            return $ok;
        } catch (Throwable $e) {
            Log::warning('Meta CAPI: error al enviar la compra', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Evento Purchase en el formato de la API de Conversiones. */
    public function purchaseEvent(Order $order): array
    {
        $contents = $order->items->map(fn ($i) => [
            'id' => (string) ($i->variant?->product?->id ?? $i->product_variant_id),
            'quantity' => (int) $i->quantity,
            'item_price' => round((float) $i->unit_price, 2),
        ])->values()->all();

        return [
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            // Igual que el navegador (lib/analytics/events.ts): así Meta deduplica.
            'event_id' => "pay-{$order->id}",
            'action_source' => 'website',
            'event_source_url' => rtrim((string) config('app.frontend_url'), '/').'/checkout/success',
            'user_data' => $this->userData($order),
            'custom_data' => [
                'currency' => 'USD',
                'value' => round((float) $order->total, 2),
                'order_id' => $order->order_number,
                'content_type' => 'product',
                'content_ids' => array_column($contents, 'id'),
                'contents' => $contents,
                'num_items' => array_sum(array_column($contents, 'quantity')),
            ],
        ];
    }

    /** Datos del comprador, normalizados y en SHA-256 como pide Meta. Nada viaja en claro. */
    private function userData(Order $order): array
    {
        $hash = fn (?string $v) => filled($v) ? hash('sha256', $v) : null;
        $limpio = fn (?string $v) => filled($v) ? mb_strtolower(trim((string) $v)) : null;

        $email = $limpio($order->emailDelCliente());
        $telefono = preg_replace('/\D+/', '', (string) ($order->address?->phone ?: $order->user?->phone));
        $nombre = trim((string) ($order->address?->full_name ?: trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? ''))));
        $partes = $nombre !== '' && $nombre !== Order::DATO_PENDIENTE ? preg_split('/\s+/', $nombre) : [];
        $ciudad = $limpio($order->address?->city);
        $ciudad = $ciudad ? preg_replace('/[^a-z]/', '', $this->sinAcentos($ciudad)) : null;
        $pais = $this->codigoPais($order->address?->country ?: $order->country);

        return array_filter([
            'em' => $email ? [$hash($email)] : null,
            'ph' => $telefono ? [$hash($telefono)] : null,
            'fn' => isset($partes[0]) ? [$hash($this->sinAcentos(mb_strtolower($partes[0])))] : null,
            'ln' => count($partes) > 1 ? [$hash($this->sinAcentos(mb_strtolower(end($partes))))] : null,
            'ct' => $ciudad ? [$hash($ciudad)] : null,
            'country' => $pais ? [$hash($pais)] : null,
            'external_id' => [$hash($order->user_id ? "user-{$order->user_id}" : "order-{$order->id}")],
        ]);
    }

    private function sinAcentos(string $v): string
    {
        return strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ã' => 'a', 'õ' => 'o', 'â' => 'a', 'ê' => 'e', 'ô' => 'o', 'à' => 'a']);
    }

    /** Código ISO de 2 letras en minúscula, a partir del nombre o del código guardado. */
    private function codigoPais(?string $pais): ?string
    {
        if (blank($pais)) {
            return null;
        }
        $p = $this->sinAcentos(mb_strtolower(trim($pais)));
        if (preg_match('/^[a-z]{2}$/', $p)) {
            return $p;
        }

        return [
            'bolivia' => 'bo', 'brasil' => 'br', 'brazil' => 'br', 'mexico' => 'mx', 'colombia' => 'co',
            'peru' => 'pe', 'chile' => 'cl', 'argentina' => 'ar', 'ecuador' => 'ec', 'paraguay' => 'py',
            'uruguay' => 'uy', 'venezuela' => 've', 'guatemala' => 'gt', 'el salvador' => 'sv', 'honduras' => 'hn',
            'nicaragua' => 'ni', 'costa rica' => 'cr', 'panama' => 'pa', 'republica dominicana' => 'do',
            'puerto rico' => 'pr', 'estados unidos' => 'us', 'united states' => 'us', 'usa' => 'us',
            'espana' => 'es', 'spain' => 'es', 'portugal' => 'pt', 'francia' => 'fr', 'italia' => 'it',
            'alemania' => 'de', 'reino unido' => 'gb', 'canada' => 'ca',
        ][$p] ?? null;
    }
}

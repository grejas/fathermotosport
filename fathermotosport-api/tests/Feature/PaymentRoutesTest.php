<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los pagos online están apagados: TODAS las rutas /payments/* devuelven 503.
 * La integración de PayPal existe en el código pero sus rutas están comentadas
 * en routes/api.php hasta configurar el servidor.
 *
 * Al reactivar PayPal hay que invertir la expectativa de paypal/create y
 * paypal/capture: deben dejar de dar 503 y llegar al controlador.
 */
class PaymentRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_payment_route_is_blocked_with_503(): void
    {
        $rutas = [
            '/api/v1/payments/paypal/create',
            '/api/v1/payments/paypal/capture/PAYPAL-ORDER-123',
            '/api/v1/payments/stripe/intent',
            '/api/v1/payments/stripe/confirm',
            '/api/v1/payments/mercadopago/create',
            '/api/v1/payments/cualquier-otra',
        ];

        foreach ($rutas as $ruta) {
            $this->postJson($ruta, [])
                ->assertStatus(503)
                ->assertJsonPath('success', false)
                ->assertJsonStructure(['message', 'whatsapp']);
        }
    }

    public function test_the_paypal_webhook_is_not_behind_the_block(): void
    {
        // El webhook no cuelga de /payments/*: sigue accesible. Sin PAYPAL_WEBHOOK_ID
        // la verificación de firma falla y responde 400, que es lo esperado.
        $this->postJson('/api/v1/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
            ->assertStatus(400);
    }
}

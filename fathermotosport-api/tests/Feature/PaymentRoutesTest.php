<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PayPal está activo; stripe y mercadopago siguen detrás del 503 hasta tener
 * credenciales. Si se vuelve a apagar PayPal (comentando sus rutas en
 * routes/api.php), hay que mover paypal/create y paypal/capture al test de
 * rutas bloqueadas.
 */
class PaymentRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_routes_are_not_blocked(): void
    {
        // Sin order_id válido responde 422 de validación: lo importante es que NO sea 503,
        // es decir, que la petición llegue al controlador y no al bloqueo.
        $this->postJson('/api/v1/payments/paypal/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        // La captura de una orden inexistente llega al controlador y da 404, no 503.
        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-INEXISTENTE')
            ->assertStatus(404);
    }

    public function test_other_payment_routes_are_still_blocked(): void
    {
        $bloqueadas = [
            '/api/v1/payments/stripe/intent',
            '/api/v1/payments/stripe/confirm',
            '/api/v1/payments/mercadopago/create',
            '/api/v1/payments/cualquier-otra',
        ];

        foreach ($bloqueadas as $ruta) {
            $this->postJson($ruta, [])
                ->assertStatus(503)
                ->assertJsonPath('success', false)
                ->assertJsonStructure(['message', 'whatsapp']);
        }
    }

    public function test_the_paypal_webhook_is_reachable(): void
    {
        // Sin PAYPAL_WEBHOOK_ID la verificación de firma falla y responde 400.
        $this->postJson('/api/v1/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
            ->assertStatus(400);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentRecoveryMail;
use App\Mail\ResetPasswordMail;
use App\Mail\ShippingUpdateMail;
use App\Mail\WelcomeEmployeeMail;
use App\Mail\WelcomeMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/**
 * El pie de TODOS los correos de la tienda: solo "FatherMotoSport" (sin ciudad ni
 * país) y el envío gratis a toda América y Europa. Ninguno puede quedar con el texto
 * viejo.
 */
class PieDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    private const VIEJOS = ['Cochabamba', 'Envío gratuito a Bolivia y Brasil', 'Frete grátis para Bolívia e Brasil', 'Free shipping to Bolivia and Brazil'];

    /** Pedido en memoria con todo lo que leen las plantillas, sin tocar la base. */
    private function pedido(?string $locale = null): Order
    {
        $order = new Order([
            'order_number' => 'FMS-0001', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0,
            'total' => 100, 'guest_email' => 'cliente@example.com', 'locale' => $locale,
        ]);
        $order->id = '00000000-0000-4000-8000-000000000001';
        $order->access_token = 'token';
        $order->setRelation('items', collect());
        $order->setRelation('address', null);
        $order->setRelation('user', null);

        return $order;
    }

    private function usuario(): User
    {
        return new User(['first_name' => 'Ana', 'email' => 'ana@example.com']);
    }

    /** @return array<string, Mailable> */
    private function correosDelLayoutBase(): array
    {
        return [
            'confirmación' => new OrderConfirmedMail($this->pedido()),
            'recibido' => new OrderReceivedMail($this->pedido()),
            'envío' => new ShippingUpdateMail($this->pedido(), 'TRK-1', 'DHL'),
            'bienvenida' => new WelcomeMail($this->usuario(), new Coupon(['code' => 'BIENVENIDO10'])),
            'empleado' => new WelcomeEmployeeMail($this->usuario(), 'Clave#Temporal1'),
            'contraseña' => new ResetPasswordMail($this->usuario(), 'https://fathermotosport.com/reset'),
            'código' => new EmailVerificationCodeMail('123456'),
        ];
    }

    private function html(Mailable $correo): string
    {
        return html_entity_decode($correo->render(), ENT_QUOTES);
    }

    public function test_every_base_layout_mail_has_the_new_footer(): void
    {
        foreach ($this->correosDelLayoutBase() as $nombre => $correo) {
            $html = $this->html($correo);

            $this->assertStringContainsString('Envío gratis a toda América y Europa', $html, $nombre);
            $this->assertMatchesRegularExpression('#<div class="footer-text">\s*FatherMotoSport<br>#', $html, $nombre);

            foreach (self::VIEJOS as $viejo) {
                $this->assertStringNotContainsString($viejo, $html, "{$nombre}: {$viejo}");
            }
        }
    }

    public function test_the_welcome_mail_promises_free_shipping_to_the_americas_and_europe(): void
    {
        $html = $this->html(new WelcomeMail($this->usuario(), new Coupon(['code' => 'BIENVENIDO10'])));
        // La frase lleva "gratis" en negrita: se compara sin etiquetas.
        $texto = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('Recordá que el envío siempre es gratis a toda América y Europa.', $texto);
        $this->assertStringNotContainsString('Bolivia y Brasil', $texto);
    }

    public function test_the_payment_recovery_mail_footer_has_only_the_store_and_the_contact(): void
    {
        // Correo transaccional: sin la línea de envío gratis ni enlaces de la tienda.
        $contacto = [
            'es' => '¿Tienes dudas? Escríbenos a',
            'pt' => 'Tem dúvidas? Escreva para',
            'en' => 'Questions? Write to us at',
        ];
        $envio = ['Envío gratis a toda América y Europa', 'Frete grátis para toda a América e Europa', 'Free shipping to all of the Americas and Europe'];

        foreach ($contacto as $locale => $frase) {
            $html = $this->html(new PaymentRecoveryMail($this->pedido($locale)));
            $pie = substr($html, strrpos($html, 'FatherMotoSport<br>'));

            $this->assertStringContainsString($frase, $pie, $locale);
            $this->assertStringContainsString('contacto@fathermotosport.com', $pie, $locale);

            foreach ([...$envio, ...self::VIEJOS] as $quitado) {
                $this->assertStringNotContainsString($quitado, $html, "{$locale}: {$quitado}");
            }
        }
    }
}

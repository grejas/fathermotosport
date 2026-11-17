<?php

namespace Tests\Feature;

use App\Mail\CorreoCuponPopup;
use App\Models\PopupLead;
use App\Support\BajaDelPopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Correos automáticos del popup: bienvenida con el cupón, recordatorio y baja. */
class PopupCorreosTest extends TestCase
{
    use RefreshDatabase;

    private function suscribir(string $email = 'cliente@gmail.com', string $locale = 'es'): PopupLead
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.random_int(1, 250)])
            ->postJson('/api/v1/popup/lead', ['email' => $email, 'locale' => $locale, 'consent' => true])
            ->assertCreated();

        return PopupLead::where('email', $email)->firstOrFail();
    }

    public function test_envia_la_bienvenida_una_sola_vez(): void
    {
        Mail::fake();
        $lead = $this->suscribir();

        $this->artisan('popup:correos')->assertSuccessful();
        $this->artisan('popup:correos')->assertSuccessful();

        Mail::assertSent(CorreoCuponPopup::class, 1);
        Mail::assertSent(CorreoCuponPopup::class, fn ($m) => $m->hasTo('cliente@gmail.com') && $m->tipo === 'bienvenida');
        $this->assertNotNull($lead->fresh()->welcome_sent_at);
    }

    public function test_el_correo_lleva_el_codigo_el_enlace_y_la_baja(): void
    {
        $lead = $this->suscribir('ana@gmail.com', 'pt');
        $correo = new CorreoCuponPopup($lead->load('coupon'), CorreoCuponPopup::BIENVENIDA);
        $html = $correo->render();

        $this->assertStringContainsString($lead->coupon->code, $html);
        $this->assertStringContainsString('cupon='.$lead->coupon->code, $correo->urlTienda());
        $this->assertStringContainsString('/pt/catalog', $correo->urlTienda());
        $this->assertStringContainsString('Seu cupom', $html);
        $this->assertStringContainsString('popup/unsubscribe', $html);
    }

    public function test_recordatorio_solo_si_vence_pronto_y_no_se_uso(): void
    {
        Mail::fake();
        $lead = $this->suscribir();
        $this->artisan('popup:correos');

        // Aún faltan 7 días: no hay recordatorio.
        $this->travel(13)->hours();
        $this->artisan('popup:correos');
        Mail::assertSent(CorreoCuponPopup::class, 1);

        // Faltan menos de 48 horas: sale el recordatorio, una vez.
        $this->travel(5)->days();
        $this->artisan('popup:correos');
        $this->artisan('popup:correos');
        Mail::assertSent(CorreoCuponPopup::class, fn ($m) => $m->tipo === 'recordatorio');
        Mail::assertSent(CorreoCuponPopup::class, 2);
    }

    public function test_sin_recordatorio_si_el_cupon_ya_se_uso(): void
    {
        Mail::fake();
        $lead = $this->suscribir();
        $this->artisan('popup:correos');
        $lead->coupon->update(['used_count' => 1]);

        $this->travel(6)->days();
        $this->artisan('popup:correos');

        Mail::assertSent(CorreoCuponPopup::class, 1);
    }

    public function test_la_baja_firmada_detiene_los_correos(): void
    {
        Mail::fake();
        $lead = $this->suscribir();

        $this->get(BajaDelPopup::url($lead))->assertOk()->assertSee('te diste de baja');
        $this->assertNotNull($lead->fresh()->unsubscribed_at);

        $this->artisan('popup:correos');
        Mail::assertNothingSent();
    }

    public function test_la_baja_sin_firma_es_rechazada_y_el_un_clic_funciona(): void
    {
        $lead = $this->suscribir();

        $this->get("/api/v1/popup/unsubscribe/{$lead->id}")->assertForbidden();
        $this->assertNull($lead->fresh()->unsubscribed_at);

        $this->post(BajaDelPopup::url($lead))->assertNoContent();
        $this->assertNotNull($lead->fresh()->unsubscribed_at);
    }

    public function test_no_escribe_a_quien_se_dio_de_baja_como_cliente(): void
    {
        Mail::fake();
        DB::table('mail_unsubscribes')->insert(['email' => 'cliente@gmail.com', 'source' => 'link', 'created_at' => now()]);
        $this->suscribir();

        $this->artisan('popup:correos');

        Mail::assertNothingSent();
    }

    public function test_apagado_por_configuracion_no_envia(): void
    {
        Mail::fake();
        config(['popup.emails_enabled' => false]);
        $this->suscribir();

        $this->artisan('popup:correos');

        Mail::assertNothingSent();
    }
}

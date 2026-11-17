<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\PopupLead;
use App\Services\CouponService;
use App\Services\PopupLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Popup de la web: email a cambio de un cupón de 10–25 %, un solo uso, sin dueño.
 */
class PopupLeadTest extends TestCase
{
    use RefreshDatabase;

    private function enviar(array $datos = [], string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/popup/lead', array_merge([
            'email' => 'cliente@gmail.com',
            'whatsapp' => '+52 55 1234 5678',
            'country' => 'mx',
            'locale' => 'es',
            'consent' => true,
            'source' => ['utm_source' => 'tiktok', 'page' => '/catalog'],
        ], $datos));
    }

    public function test_entrega_un_cupon_valido_entre_10_y_25(): void
    {
        $res = $this->enviar()->assertCreated()->assertJsonPath('status', 'new');

        $this->assertMatchesRegularExpression('/^WEB-[A-Z0-9]{6}$/', $res->json('code'));
        $this->assertContains($res->json('percent'), [10, 15, 20, 25]);

        $cupon = Coupon::where('code', $res->json('code'))->firstOrFail();
        $this->assertSame('percentage', $cupon->type);
        $this->assertSame(1, $cupon->max_uses);
        $this->assertNull($cupon->user_id, 'Debe servir en compras sin cuenta');
        $this->assertTrue($cupon->isValid());
        $this->assertTrue($cupon->expires_at->between(now()->addDays(6), now()->addDays(8)));

        $lead = PopupLead::firstOrFail();
        $this->assertSame('MX', $lead->country);
        $this->assertSame('tiktok', $lead->source['utm_source']);
        $this->assertTrue($lead->consent);
        $this->assertNotSame('10.0.0.1', $lead->ip_hash, 'No se guarda la IP en claro');
    }

    public function test_el_cupon_sirve_sin_sesion_en_la_validacion(): void
    {
        $code = $this->enviar()->json('code');

        $this->postJson('/api/v1/coupons/validate', ['code' => $code, 'amount' => 200])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_mismo_email_recibe_el_mismo_cupon_y_no_otro_porcentaje(): void
    {
        $primero = $this->enviar(['email' => 'Cliente@Gmail.com'])->json();
        $segundo = $this->enviar(['email' => 'cliente@gmail.com'])->assertOk()->json();

        $this->assertSame('existing', $segundo['status']);
        $this->assertSame($primero['code'], $segundo['code']);
        $this->assertSame($primero['percent'], $segundo['percent']);
        $this->assertSame(1, PopupLead::count());
    }

    public function test_si_ya_uso_su_cupon_no_recibe_otro(): void
    {
        $code = $this->enviar()->json('code');
        Coupon::where('code', $code)->update(['used_count' => 1]);

        $this->enviar()->assertStatus(409)->assertJsonPath('status', 'already_used');
        $this->assertSame(1, Coupon::where('code', 'like', 'WEB-%')->count());
    }

    public function test_limite_de_registros_nuevos_por_ip(): void
    {
        config(['popup.max_per_ip_per_day' => 2]);

        $this->enviar(['email' => 'a@gmail.com'])->assertCreated();
        $this->enviar(['email' => 'b@gmail.com'])->assertCreated();
        $this->enviar(['email' => 'c@gmail.com'])->assertStatus(429)->assertJsonPath('status', 'limited');
        $this->enviar(['email' => 'd@gmail.com'], '10.0.0.2')->assertCreated();
    }

    public function test_exige_consentimiento_y_rechaza_bots(): void
    {
        $this->enviar(['consent' => false])->assertUnprocessable();
        $this->enviar(['website' => 'http://spam'])->assertUnprocessable();
        $this->enviar(['email' => 'no-es-email'])->assertUnprocessable();
        $this->assertSame(0, PopupLead::count());
    }

    public function test_los_pesos_respetan_los_porcentajes_configurados(): void
    {
        config(['popup.weights' => [25 => 1]]);
        $this->assertSame(25, app(PopupLeadService::class)->sortearPorcentaje());

        config(['popup.weights' => [10 => 50, 15 => 30, 20 => 15, 25 => 5]]);
        $vistos = [];
        for ($i = 0; $i < 400; $i++) {
            $vistos[app(PopupLeadService::class)->sortearPorcentaje()] = true;
        }
        $this->assertEqualsCanonicalizing([10, 15, 20, 25], array_keys($vistos));
    }

    public function test_cupon_del_popup_tiene_prefijo_propio(): void
    {
        $cupon = app(CouponService::class)->generatePopupCoupon(15, 7);
        $this->assertStringStartsWith('WEB-', $cupon->code);
        $this->assertEquals(15, $cupon->value);
    }
}

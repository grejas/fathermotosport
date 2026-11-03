<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\MailCampaignResource\Pages\ListMailCampaigns;
use App\Mail\CorreoAvisoDescuento;
use App\Mail\CorreoPromocional;
use App\Models\Category;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AvisoDescuento;
use App\Services\EnvioPorTandas;
use App\Services\EnvioPromocional;
use App\Services\PrecioFinal;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Aviso por correo al activar un descuento de categoría (remarketing, fase 3): un aviso
 * por descuento, envío por tandas con cupo, cancelación si el descuento deja de valer,
 * reintentos e historial.
 */
class AvisoDescuentoTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/La_Paz';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
        config(['app.frontend_url' => 'https://web.test', 'marketing.limits.per_minute' => 60, 'marketing.limits.per_day' => 100]);
        $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', self::ZONA));
        $this->admin = User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    private function categoria(array $datos = []): Category
    {
        return Category::create([
            'name' => 'Cascos Pro', 'slug' => 'cascos-pro', 'is_active' => true,
            'discount_percent' => 15, 'discount_active' => true,
            'discount_starts_at' => now()->subDay(), 'discount_ends_at' => now()->addDays(5),
            'notify_customers' => true,
            ...$datos,
        ]);
    }

    private function cliente(string $email, array $datos = []): User
    {
        $user = (new User)->forceFill([
            'role_id' => Role::where('slug', $datos['rol'] ?? 'cliente')->firstOrFail()->id,
            'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $email,
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active',
            'email_verified_at' => now(), 'email_verified_via' => 'code',
            ...array_diff_key($datos, ['rol' => true]),
        ]);
        $user->save();

        return $user;
    }

    private function avisos(): AvisoDescuento
    {
        return app(AvisoDescuento::class);
    }

    private function tanda(): array
    {
        return app(EnvioPorTandas::class)->procesar();
    }

    private function editar(Category $categoria)
    {
        $this->actingAs($this->admin);

        return Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()]);
    }

    // ── Interruptor y acción ────────────────────────────────────────────────

    public function test_con_el_interruptor_apagado_no_se_puede_enviar(): void
    {
        $categoria = $this->categoria(['notify_customers' => false]);
        $this->cliente('ana@gmail.com');

        $this->editar($categoria)->assertActionHidden('avisar_por_correo')->assertActionHidden('probar_aviso');

        $this->expectExceptionMessage('El interruptor "Avisar a los clientes por correo" está apagado.');
        $this->avisos()->crear($categoria, $this->admin);
    }

    public function test_encender_el_interruptor_no_envia_nada(): void
    {
        $categoria = $this->categoria(['notify_customers' => false]);
        $this->cliente('ana@gmail.com');

        $categoria->update(['notify_customers' => true]);
        $this->artisan('campanas:enviar')->assertSuccessful();

        $this->editar($categoria)->assertActionVisible('avisar_por_correo');
        $this->assertSame(0, MailCampaign::count());
        Mail::assertNothingSent();
    }

    public function test_la_accion_pide_confirmacion_explicita_y_registra_el_aviso(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $this->cliente('beto@gmail.com');

        $this->editar($categoria)
            ->mountAction('avisar_por_correo')
            ->assertSee('clientes lo recibirían')
            ->assertSee('Voy a enviar este correo a 2 clientes');

        $this->editar($categoria)->callAction('avisar_por_correo', ['confirmo' => false])->assertHasActionErrors(['confirmo']);
        $this->assertSame(0, MailCampaign::count());

        $this->editar($categoria)->callAction('avisar_por_correo', ['confirmo' => true])->assertNotified('Aviso registrado');

        $campana = MailCampaign::sole();
        $this->assertSame(2, $campana->total_count);
        $this->assertSame('category:'.$categoria->id.':15.00:'.now()->subDay()->utc()->format('Y-m-d\TH:i').':'.now()->addDays(5)->utc()->format('Y-m-d\TH:i'), $campana->dedupe_key);
        // Registrar no envía: lo hace el comando por tandas.
        Mail::assertNothingSent();

        $this->artisan('campanas:enviar')->assertSuccessful();
        Mail::assertSent(CorreoAvisoDescuento::class, 2);
    }

    // ── Una sola vez por descuento ──────────────────────────────────────────

    public function test_un_descuento_se_avisa_una_sola_vez(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $this->avisos()->crear($categoria, $this->admin);

        $this->editar($categoria)->mountAction('avisar_por_correo')->assertSee('Este descuento ya se avisó');

        try {
            $this->avisos()->crear($categoria, $this->admin);
            $this->fail('no debía crear otro aviso');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ya se avisó', $e->getMessage());
        }
        $this->assertSame(1, MailCampaign::count());
    }

    public function test_editar_otros_campos_o_apagar_y_encender_no_reenvia(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $this->avisos()->crear($categoria, $this->admin);

        $categoria->update(['name' => 'Cascos Pro 2', 'description' => 'Otra descripción', 'sort_order' => 9]);
        $categoria->update(['discount_active' => false]);
        $categoria->update(['discount_active' => true]);

        $this->expectException(RuntimeException::class);
        $this->avisos()->crear($categoria->fresh(), $this->admin);
    }

    public function test_cambiar_el_porcentaje_es_otro_descuento(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $this->avisos()->crear($categoria, $this->admin);

        $categoria->update(['discount_percent' => 20]);
        $this->avisos()->crear($categoria->fresh(), $this->admin);

        $this->assertSame(2, MailCampaign::count());
    }

    public function test_reenviar_repite_el_envio_con_su_propia_confirmacion(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');

        $this->editar($categoria)->assertActionHidden('reenviar_aviso');
        $this->avisos()->crear($categoria, $this->admin);
        $this->tanda();

        $this->editar($categoria)->callAction('reenviar_aviso', ['confirmo' => false])->assertHasActionErrors(['confirmo']);
        $this->editar($categoria)->callAction('reenviar_aviso', ['confirmo' => true])->assertNotified('Reenvío registrado');
        $this->tanda();

        $this->assertSame(2, MailCampaign::count());
        $this->assertStringEndsWith(':reenvio:1', MailCampaign::latest('id')->first()->dedupe_key);
        Mail::assertSent(CorreoAvisoDescuento::class, 2);
    }

    // ── Descuento que no vale ───────────────────────────────────────────────

    public function test_vencido_apagado_o_futuro_no_se_envia(): void
    {
        $this->cliente('ana@gmail.com');
        $casos = [
            'vencido' => [['discount_ends_at' => now()->subHour()], 'El descuento ya venció.'],
            'apagado' => [['discount_active' => false], 'El descuento está apagado o no tiene porcentaje.'],
            'futuro' => [['discount_starts_at' => now()->addDay()], 'El descuento todavía no empezó: se puede avisar cuando esté vigente.'],
        ];

        foreach ($casos as $nombre => [$datos, $mensaje]) {
            $categoria = $this->categoria([...$datos, 'slug' => "cat-{$nombre}"]);
            try {
                $this->avisos()->crear($categoria, $this->admin);
                $this->fail("{$nombre}: no debía crear el aviso");
            } catch (RuntimeException $e) {
                $this->assertSame($mensaje, $e->getMessage(), $nombre);
            }
            $this->editar($categoria)->mountAction('avisar_por_correo')->assertSee('No se puede enviar');
        }

        $this->assertSame(0, MailCampaign::count());
    }

    public function test_si_vence_a_mitad_del_envio_se_cancela_el_resto(): void
    {
        $categoria = $this->categoria(['discount_ends_at' => now()->addMinutes(30)]);
        foreach (['a', 'b', 'c'] as $l) {
            $this->cliente("{$l}@gmail.com");
        }
        $campana = $this->avisos()->crear($categoria, $this->admin);

        // Después del primer correo, el reloj pasa la fecha de fin.
        $this->app->instance(EnvioPromocional::class, new class extends EnvioPromocional
        {
            public function enviar(CorreoPromocional $correo): array
            {
                $r = parent::enviar($correo);
                Carbon::setTestNow(now()->addHour());

                return $r;
            }
        });
        $this->tanda();

        $campana->refresh();
        $this->assertSame(MailCampaign::CANCELADA, $campana->status);
        $this->assertSame('El descuento venció durante el envío.', $campana->cancel_reason);
        $this->assertSame(1, $campana->sent_count);
        $this->assertSame(2, MailCampaignRecipient::where('status', MailCampaignRecipient::CANCELADO)->count());
        Mail::assertSent(CorreoAvisoDescuento::class, 1);
    }

    // ── Destinatarios ───────────────────────────────────────────────────────

    public function test_solo_reciben_los_clientes_con_criterio_fiable(): void
    {
        $categoria = $this->categoria();
        $this->cliente('codigo@gmail.com');
        $this->cliente('google@gmail.com', ['email_verified_via' => null, 'avatar' => 'https://lh3.googleusercontent.com/x']);
        $this->cliente('sinrastro@gmail.com', ['email_verified_via' => null]);
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $this->cliente('rebote@gmail.com', ['email_bounced_at' => now()]);
        $this->cliente('inactivo@gmail.com', ['status' => 'inactive']);
        $this->cliente('borrado@gmail.com')->delete();

        $this->editar($categoria)->mountAction('avisar_por_correo')->assertSee('Voy a enviar este correo a 2 clientes');
        $campana = $this->avisos()->crear($categoria, $this->admin);
        $this->assertSame(1, $this->avisos()->excluidosSinEvidencia());
        $this->tanda();

        $this->assertEqualsCanonicalizing(['codigo@gmail.com', 'google@gmail.com'], $campana->recipients()->pluck('email')->all());
        Mail::assertSent(CorreoAvisoDescuento::class, 2);
    }

    public function test_una_baja_entre_el_registro_y_el_envio_se_omite(): void
    {
        $categoria = $this->categoria();
        $ana = $this->cliente('ana@gmail.com');
        $this->cliente('beto@gmail.com');
        $campana = $this->avisos()->crear($categoria, $this->admin);

        $ana->forceFill(['promo_unsubscribed_at' => now()])->save();
        $this->tanda();

        $this->assertSame('omitido: dado de baja', $campana->recipients()->where('user_id', $ana->id)->value('reason'));
        $this->assertSame(1, app(EnvioPromocional::class)->enviadosHoy());
        $this->assertSame(MailCampaign::COMPLETADA, $campana->fresh()->status);
    }

    // ── Contenido ───────────────────────────────────────────────────────────

    public function test_el_correo_en_es_pt_en_con_categoria_porcentaje_fechas_y_boton(): void
    {
        $categoria = $this->categoria([
            'discount_percent' => 7.5,
            'discount_starts_at' => CarbonImmutable::parse('2027-03-09 08:00', self::ZONA)->utc(),
            'discount_ends_at' => CarbonImmutable::parse('2027-03-15 23:59', self::ZONA)->utc(),
        ]);
        $categoria->productosExcluidos()->attach(Product::factory()->create(['category_id' => $categoria->id])->id);

        $casos = [
            'es' => ['Cascos Pro con 7,5 % menos: solo hasta el 15 de marzo', ['-7,5 % en Cascos Pro', 'Protección y calidad para cada salida', 'Válido hasta el 15 de marzo de 2027.', 'No incluye algunos productos.', 'Ver las ofertas en Cascos Pro', 'https://web.test/catalog?category='.$categoria->id, 'no necesitas ningún código', 'Envío gratis a toda América y Europa', 'Pago seguro', 'Recibes este correo porque tienes una cuenta en FatherMotoSport', 'Darte de baja de las promociones']],
            'pt' => ['Cascos Pro com 7,5% de desconto: só até 15 de março', ['-7,5% em Cascos Pro', 'Válido até 15 de março de 2027.', 'Não inclui alguns produtos.', 'Ver as ofertas em Cascos Pro', 'https://web.test/pt/catalog?category='.$categoria->id, 'não precisa de nenhum código', 'Frete grátis para toda a América e Europa', 'Pagamento seguro']],
            'en' => ['7.5% off Cascos Pro: only until March 15', ['7.5% off Cascos Pro', 'Valid until March 15, 2027.', 'Some products are not included.', 'See the deals on Cascos Pro', 'https://web.test/en/catalog?category='.$categoria->id, 'no code needed', 'Free shipping to all of the Americas and Europe', 'Secure payment']],
        ];

        foreach ($casos as $idioma => [$asunto, $esperados]) {
            $user = $this->cliente("c-{$idioma}@gmail.com", ['locale' => $idioma]);
            $correo = new CorreoAvisoDescuento($user, $this->avisos()->datos($categoria), 'campaign:1');
            $html = html_entity_decode($correo->render(), ENT_QUOTES);

            $this->assertSame($asunto, $correo->envelope()->subject, $idioma);
            foreach ([...$esperados, '/baja?u='.$user->id, 'mailto:'] as $texto) {
                $this->assertStringContainsString($texto, $html, "{$idioma}: {$texto}");
            }
            // No es un cupón.
            foreach (['cupón', 'cupom', 'coupon', 'BDAY-'] as $palabra) {
                $this->assertStringNotContainsStringIgnoringCase($palabra, strip_tags($html), "{$idioma}: {$palabra}");
            }
            // La vigencia es solo la fecha: sin hora ni zona.
            foreach (['23:59', '11:59', 'Bolivia', 'Bolívia', 'La Paz'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $html, "{$idioma}: {$prohibido}");
            }
            $cabeceras = $correo->headers()->text;
            $this->assertSame('campaign:1:'.$user->id, $cabeceras['Resend-Idempotency-Key']);
            $this->assertStringContainsString('/api/v1/', $cabeceras['List-Unsubscribe']);
            $this->assertSame('List-Unsubscribe=One-Click', $cabeceras['List-Unsubscribe-Post']);
        }
    }

    public function test_sin_fecha_de_fin_no_hay_urgencia_ni_nota_de_excluidos(): void
    {
        $categoria = $this->categoria(['discount_ends_at' => null]);

        foreach (['es' => 'Cascos Pro con 15 % menos en FatherMotoSport', 'pt' => 'Cascos Pro com 15% de desconto na FatherMotoSport', 'en' => '15% off Cascos Pro at FatherMotoSport'] as $idioma => $asunto) {
            $correo = $this->avisos()->correoDePrueba($categoria, $idioma);
            $texto = strip_tags(html_entity_decode($correo->render(), ENT_QUOTES));

            $this->assertSame($asunto, $correo->envelope()->subject);
            foreach (['tiempo limitado', 'tempo limitado', 'limited time', 'Válido', 'Valid until', 'hasta el', 'só até', 'No incluye', 'Não inclui', 'not included'] as $prohibido) {
                $this->assertStringNotContainsStringIgnoringCase($prohibido, $texto, "{$idioma}: {$prohibido}");
            }
        }
    }

    public function test_un_fin_a_medianoche_es_el_dia_anterior_en_el_asunto(): void
    {
        $categoria = $this->categoria(['discount_ends_at' => CarbonImmutable::parse('2027-03-16 00:00', self::ZONA)->utc()]);

        $correo = $this->avisos()->correoDePrueba($categoria, 'es');
        $this->assertSame('Cascos Pro con 15 % menos: solo hasta el 15 de marzo', $correo->envelope()->subject);
        $this->assertStringContainsString('Válido hasta el 15 de marzo de 2027.', html_entity_decode($correo->render(), ENT_QUOTES));
    }

    public function test_el_aviso_conserva_el_enlace_de_baja_visible(): void
    {
        $categoria = $this->categoria();

        foreach (['es', 'pt', 'en'] as $idioma) {
            $user = $this->cliente("baja-{$idioma}@gmail.com", ['locale' => $idioma]);
            $html = html_entity_decode((new CorreoAvisoDescuento($user, $this->avisos()->datos($categoria), 'campaign:1'))->render(), ENT_QUOTES);

            $this->assertMatchesRegularExpression('#<a href="[^"]*/baja\?u='.$user->id.'[^"]*"[^>]*>'.preg_quote(__('marketing.unsubscribe', [], $idioma), '#').'</a>#', $html, $idioma);
        }
    }

    // ── Productos destacados y banner ───────────────────────────────────────

    private function producto(Category $categoria, array $datos = [], int $stock = 5, ?string $imagen = 'https://cdn.example.com/fotos/casco rojo.jpg'): Product
    {
        $producto = Product::factory()->create(['category_id' => $categoria->id, 'price' => 100, 'sale_price' => null, 'is_active' => true, ...$datos]);
        // La fábrica crea variantes con stock y una imagen de muestra: se reemplazan.
        $producto->variants()->delete();
        $producto->images()->delete();
        $producto->variants()->create(['sku' => 'V-'.$producto->id, 'size' => 'M', 'stock' => $stock, 'is_active' => true]);
        if ($imagen) {
            $producto->images()->create(['url' => $imagen, 'is_primary' => true, 'sort_order' => 0]);
        }

        return $producto;
    }

    private function vendido(Product $producto, int $cantidad): void
    {
        $pedido = Order::create(['status' => 'processing', 'payment_status' => 'paid', 'shipping_status' => 'pending', 'payment_method' => 'paypal', 'country' => 'Bolivia', 'guest_email' => 'invitado@example.com', 'total' => 100]);
        OrderItem::create(['order_id' => $pedido->id, 'product_variant_id' => $producto->variants()->first()->id, 'quantity' => $cantidad, 'unit_price' => 85, 'subtotal' => 85 * $cantidad]);
    }

    public function test_muestra_hasta_4_productos_con_los_precios_de_precio_final(): void
    {
        $categoria = $this->categoria();
        $otra = Category::create(['name' => 'Otra', 'slug' => 'otra', 'is_active' => true]);

        $validos = collect([
            $this->producto($categoria, ['name' => 'Casco Uno', 'created_at' => now()->subDays(5)]),
            $this->producto($categoria, ['name' => 'Casco Dos', 'price' => 200, 'created_at' => now()->subDays(4)]),
            $this->producto($categoria, ['name' => 'Casco Tres', 'price' => 99.99, 'sale_price' => 70, 'created_at' => now()->subDays(3)]),
            $this->producto($categoria, ['name' => 'Casco Cuatro', 'created_at' => now()->subDays(2)]),
            $this->producto($categoria, ['name' => 'Casco Cinco', 'created_at' => now()->subDays(6)]),
        ]);
        $excluido = $this->producto($categoria, ['name' => 'Casco Excluido']);
        $categoria->productosExcluidos()->attach($excluido->id);
        $this->producto($categoria, ['name' => 'Casco Agotado'], stock: 0);
        $this->producto($categoria, ['name' => 'Casco Inactivo', 'is_active' => false]);
        $this->producto($otra, ['name' => 'Casco De Otra']);
        // El más vendido va primero aunque sea el más viejo.
        $this->vendido($validos[4], 3);

        $correo = $this->avisos()->correoDePrueba($categoria->fresh(), 'es');
        $productos = $correo->productos();
        $html = html_entity_decode($correo->render(), ENT_QUOTES);

        $this->assertSame(['Casco Cinco', 'Casco Cuatro', 'Casco Tres', 'Casco Dos'], array_column($productos, 'nombre'));
        foreach (['Casco Excluido', 'Casco Agotado', 'Casco Inactivo', 'Casco De Otra', 'Casco Uno'] as $fuera) {
            $this->assertStringNotContainsString($fuera, $html);
        }

        $precios = app(PrecioFinal::class);
        foreach ($productos as $p) {
            $producto = Product::where('name', $p['nombre'])->firstOrFail();
            $this->assertSame($precios->de($producto)['final_price'], $p['precio_final'], $p['nombre']);
            $this->assertSame(number_format((float) $producto->price, 2, '.', ''), $p['precio']);
            $this->assertStringContainsString('<span style="text-decoration:line-through;">$'.number_format((float) $producto->price, 2).'</span>', $html);
            $this->assertStringContainsString('$'.number_format((float) $p['precio_final'], 2), $html);
            $this->assertStringContainsString('https://web.test/product/'.$producto->slug, $html);
        }
        // 15 % sobre 200 = 170; con precio propio 70 < 99,99 - 15 % gana el propio.
        $this->assertSame(['85.00', '85.00', '70.00', '170.00'], array_column($productos, 'precio_final'));
        $this->assertSame('https://cdn.example.com/fotos/casco%20rojo.jpg', $productos[0]['imagen']);
        $this->assertStringContainsString('src="https://cdn.example.com/fotos/casco%20rojo.jpg"', $html);
        $this->assertStringContainsString('alt="Casco Cinco"', $html);
    }

    public function test_los_productos_con_foto_van_primero(): void
    {
        $categoria = $this->categoria();
        $sinFoto = $this->producto($categoria, ['name' => 'Casco Sin Foto'], imagen: null);
        $this->producto($categoria, ['name' => 'Casco Con Foto', 'created_at' => now()->subDays(3)]);
        $this->vendido($sinFoto, 5);

        $this->assertSame(['Casco Con Foto', 'Casco Sin Foto'], array_column($this->avisos()->correoDePrueba($categoria, 'es')->productos(), 'nombre'));
    }

    public function test_con_un_producto_o_sin_productos_el_correo_sigue_bien(): void
    {
        $categoria = $this->categoria();

        $sin = html_entity_decode($this->avisos()->correoDePrueba($categoria, 'es')->render(), ENT_QUOTES);
        $this->assertStringNotContainsString('Destacados con descuento', $sin);
        $this->assertStringContainsString('Ver las ofertas en Cascos Pro', $sin);
        $this->assertStringContainsString('/baja?u=', $sin);

        $this->producto($categoria, ['name' => 'Casco Solo'], imagen: null);
        $uno = html_entity_decode($this->avisos()->correoDePrueba($categoria, 'es')->render(), ENT_QUOTES);
        $this->assertStringContainsString('Destacados con descuento', $uno);
        $this->assertSame(1, substr_count($uno, 'Ver producto'));
        $this->assertStringNotContainsString('<img', $uno);
    }

    public function test_banner_con_la_imagen_de_la_categoria_o_bloque_con_el_porcentaje(): void
    {
        $sinImagen = html_entity_decode($this->avisos()->correoDePrueba($this->categoria(), 'es')->render(), ENT_QUOTES);
        $this->assertStringContainsString('class="porcentaje"', $sinImagen);
        $this->assertStringContainsString('-15 %', $sinImagen);

        $conImagen = $this->categoria(['slug' => 'con-imagen', 'image_url' => 'https://cdn.example.com/categorias/cascos pro.png']);
        $html = html_entity_decode($this->avisos()->correoDePrueba($conImagen, 'es')->render(), ENT_QUOTES);
        $this->assertStringContainsString('src="https://cdn.example.com/categorias/cascos%20pro.png" width="600" alt="Cascos Pro"', $html);
        $this->assertStringNotContainsString('class="porcentaje"', $html);
        preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $m);
        foreach ($m[1] as $src) {
            $this->assertStringStartsWith('https://', $src);
        }
    }

    // ── Tandas, cupo, fallos e idempotencia ─────────────────────────────────

    public function test_respeta_el_cupo_por_minuto_y_diario_y_sigue_al_dia_siguiente(): void
    {
        config(['marketing.limits.per_minute' => 2, 'marketing.limits.per_day' => 3]);
        $categoria = $this->categoria(['discount_ends_at' => now()->addDays(5)]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $l) {
            $this->cliente("{$l}@gmail.com");
        }
        $campana = $this->avisos()->crear($categoria, $this->admin);

        $this->tanda();
        $this->assertSame(2, Mail::sent(CorreoAvisoDescuento::class)->count(), 'límite por minuto');
        $this->tanda();
        $this->assertSame(2, Mail::sent(CorreoAvisoDescuento::class)->count(), 'mismo minuto: nada más');

        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame(3, Mail::sent(CorreoAvisoDescuento::class)->count(), 'tope diario');
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame(3, Mail::sent(CorreoAvisoDescuento::class)->count());
        $this->assertSame(2, $campana->fresh()->pending_count);

        $this->travelTo(CarbonImmutable::parse('2027-03-11 09:00:00', self::ZONA));
        $this->tanda();
        $this->assertSame(5, Mail::sent(CorreoAvisoDescuento::class)->count());

        foreach (['a', 'b', 'c', 'd', 'e'] as $l) {
            $this->assertSame(1, Mail::sent(CorreoAvisoDescuento::class, fn ($m) => $m->hasTo("{$l}@gmail.com"))->count(), $l);
        }
        $this->assertSame(MailCampaign::COMPLETADA, $campana->fresh()->status);
    }

    public function test_un_fallo_no_detiene_al_resto_y_se_reintenta_hasta_3_veces(): void
    {
        $categoria = $this->categoria();
        $this->cliente('falla@gmail.com');
        $this->cliente('bien1@gmail.com');
        $this->cliente('bien2@gmail.com');
        $campana = $this->avisos()->crear($categoria, $this->admin);

        $this->app->instance(EnvioPromocional::class, new class extends EnvioPromocional
        {
            public function enviar(CorreoPromocional $correo): array
            {
                return $correo->destinatario->email === 'falla@gmail.com'
                    ? ['estado' => 'fallido', 'motivo' => 'error al enviar']
                    : parent::enviar($correo);
            }
        });

        $this->tanda();
        $falla = $campana->recipients()->where('email', 'falla@gmail.com')->sole();
        $this->assertSame([MailCampaignRecipient::PENDIENTE, 1], [$falla->status, $falla->attempts]);
        Mail::assertSent(CorreoAvisoDescuento::class, 2);

        $this->tanda();
        $this->tanda();
        $falla->refresh();
        $this->assertSame([MailCampaignRecipient::FALLIDO, 3], [$falla->status, $falla->attempts]);
        $this->tanda();
        $this->assertSame(3, $falla->fresh()->attempts, 'no pasa de 3');

        $campana->refresh();
        $this->assertSame([MailCampaign::COMPLETADA, 2, 1], [$campana->status, $campana->sent_count, $campana->failed_count]);
    }

    public function test_cada_destinatario_recibe_una_sola_vez(): void
    {
        $categoria = $this->categoria();
        $ana = $this->cliente('ana@gmail.com');
        $campana = $this->avisos()->crear($categoria, $this->admin);

        $this->tanda();
        $this->tanda();
        $this->artisan('campanas:enviar')->assertSuccessful();
        Mail::assertSent(CorreoAvisoDescuento::class, 1);

        // La base no admite el mismo cliente dos veces en un envío.
        $this->expectException(UniqueConstraintViolationException::class);
        MailCampaignRecipient::create(['mail_campaign_id' => $campana->id, 'user_id' => $ana->id, 'email' => $ana->email]);
    }

    public function test_un_destinatario_atascado_vuelve_a_pendiente_y_se_envia_sin_duplicar(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $campana = $this->avisos()->crear($categoria, $this->admin);
        // Un proceso caído lo dejó "enviando" hace 11 minutos.
        DB::table('mail_campaign_recipients')->update(['status' => 'enviando', 'claim_token' => 'x', 'updated_at' => now()->subMinutes(11)]);

        $this->tanda();

        Mail::assertSent(CorreoAvisoDescuento::class, 1);
        $this->assertSame(MailCampaign::COMPLETADA, $campana->fresh()->status);
    }

    // ── Historial y prueba ──────────────────────────────────────────────────

    public function test_el_historial_muestra_estado_y_contadores(): void
    {
        $categoria = $this->categoria();
        $this->cliente('ana@gmail.com');
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $campana = $this->avisos()->crear($categoria, $this->admin);
        $this->tanda();

        $this->actingAs($this->admin);
        $campana->refresh();
        $this->assertSame([1, 0, 0, 1], [$campana->sent_count, $campana->pending_count, $campana->failed_count, $campana->total_count], 'contadores internos');

        Livewire::test(ListMailCampaigns::class)
            ->assertCanSeeTableRecords([$campana])
            ->assertTableColumnStateSet('status', MailCampaign::COMPLETADA, $campana->getKey())
            ->assertTableColumnStateSet('type', MailCampaign::TIPO_DESCUENTO, $campana->getKey())
            ->assertSee('1 de 1 enviados')
            ->assertDontSee('fallido');
    }

    public function test_el_envio_de_prueba_no_cuenta_ni_queda_como_destinatario(): void
    {
        $categoria = $this->categoria();

        $this->editar($categoria)
            ->callAction('probar_aviso', ['email' => 'probador@gmail.com', 'idioma' => 'pt'])
            ->assertNotified('Prueba enviada a probador@gmail.com');

        Mail::assertSent(CorreoAvisoDescuento::class, fn ($m) => $m->hasTo('probador@gmail.com') && $m->idioma() === 'pt');
        $this->assertSame(0, app(EnvioPromocional::class)->enviadosHoy());
        $this->assertSame(0, MailCampaign::count());
        $this->assertSame(0, MailCampaignRecipient::count());
    }

    public function test_un_empleado_no_ve_las_acciones_de_aviso(): void
    {
        $categoria = $this->categoria();
        $empleado = $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado']);

        $this->actingAs($empleado);
        Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
            ->assertActionHidden('avisar_por_correo')
            ->assertActionHidden('probar_aviso');
    }

    public function test_los_precios_no_cambian(): void
    {
        $categoria = $this->categoria();
        $producto = Product::factory()->create(['category_id' => $categoria->id, 'price' => 100, 'sale_price' => null]);
        $antes = $this->getJson("/api/v1/products/{$producto->slug}")->json('data.final_price');

        $this->cliente('ana@gmail.com');
        $this->avisos()->crear($categoria, $this->admin);
        $this->tanda();

        $this->assertSame($antes, $this->getJson("/api/v1/products/{$producto->slug}")->json('data.final_price'));
        $this->assertSame('85.00', $antes);
    }
}

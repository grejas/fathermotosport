<?php

namespace Tests\Feature;

use App\Services\FrontendRevalidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * El secreto de revalidación se limpia antes de usarlo y nunca llega a un log: ni
 * entero, ni la cabecera, ni un trozo. Lo usan banners, categorías, envíos y
 * devoluciones, y Configuración de la tienda.
 */
class FrontendRevalidatorTest extends TestCase
{
    private const SECRETO = 'S3cr3t0-De-Prueba-9f2c7a41b8e6d035';

    private const URL = 'https://web.test/api/revalidate';

    /** Todo lo que se escribe en el log durante el test, como texto. */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://web.test/', 'services.revalidate.secret' => self::SECRETO]);

        Log::listen(function ($evento) {
            $this->log[] = $evento->level.' '.$evento->message.' '.json_encode($evento->context, JSON_UNESCAPED_SLASHES);
        });
    }

    private function revalidar(string $tag = 'store-settings'): bool
    {
        return app(FrontendRevalidator::class)->revalidate($tag);
    }

    /** Ni el secreto, ni un trozo de 6 caracteres, ni la cabecera aparecen en el log. */
    private function assertLogSinSecreto(): void
    {
        $texto = implode("\n", $this->log);
        $this->assertNotSame('', $texto, 'Se esperaba al menos una línea de log.');

        foreach (str_split(self::SECRETO, 6) as $trozo) {
            $this->assertStringNotContainsString($trozo, $texto);
        }
        $this->assertStringNotContainsString('x-revalidate-secret', $texto);
    }

    public static function finesDeLinea(): array
    {
        return ['\r\n' => ["\r\n"], '\n' => ["\n"], '\r' => ["\r"], 'espacios' => ["  \t"]];
    }

    #[DataProvider('finesDeLinea')]
    public function test_un_secreto_con_fin_de_linea_se_limpia_y_la_peticion_funciona(string $fin): void
    {
        config(['services.revalidate.secret' => self::SECRETO.$fin]);
        Http::fake([self::URL => Http::response(['revalidated' => true])]);

        $this->assertTrue($this->revalidar());

        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->header('x-revalidate-secret') === [self::SECRETO]
            && $request['tag'] === 'store-settings');
        $this->assertSame([], $this->log);
    }

    public static function fallos(): array
    {
        return [
            '400' => [fn () => Http::response(['message' => 'Unknown tag', 'echo' => self::SECRETO], 400), 400],
            '500' => [fn () => Http::response('boom '.self::SECRETO, 500), 500],
            'timeout' => [fn () => throw new ConnectionException('cURL error 28: timed out; x-revalidate-secret: '.self::SECRETO), null],
            'cabecera inválida' => [fn () => throw new RuntimeException('"'.self::SECRETO.'" is not valid header value.'), null],
        ];
    }

    #[DataProvider('fallos')]
    public function test_un_fallo_no_deja_el_secreto_en_el_log(\Closure $respuesta, ?int $estado): void
    {
        Http::fake(['web.test/*' => $respuesta]);

        $this->assertFalse($this->revalidar('banners'));

        $this->assertLogSinSecreto();
        $this->assertCount(1, $this->log);
        $this->assertStringStartsWith('error ', $this->log[0]);
        $this->assertStringEndsWith(json_encode(['tag' => 'banners', 'url' => self::URL, 'status' => $estado], JSON_UNESCAPED_SLASHES), $this->log[0]);
    }

    public function test_la_url_del_log_va_sin_usuario_ni_contrasena(): void
    {
        config(['app.frontend_url' => 'https://usuario:clave@web.test:8443']);
        Http::fake(fn () => Http::response('', 500));

        $this->assertFalse($this->revalidar());

        $this->assertStringContainsString('"url":"https://web.test:8443/api/revalidate"', $this->log[0]);
        $this->assertStringNotContainsString('usuario', $this->log[0]);
        $this->assertStringNotContainsString('clave', $this->log[0]);
    }

    public function test_la_url_del_log_va_sin_parametros(): void
    {
        config(['app.frontend_url' => 'https://web.test/?token='.self::SECRETO]);
        Http::fake(fn () => Http::response('', 500));

        $this->assertFalse($this->revalidar());

        $this->assertLogSinSecreto();
        $this->assertStringNotContainsString('token', $this->log[0]);
    }

    public static function secretosVacios(): array
    {
        return ['null' => [null], 'vacío' => [''], 'solo saltos' => ["\r\n"], 'espacios' => ['   ']];
    }

    #[DataProvider('secretosVacios')]
    public function test_sin_secreto_no_llama_a_la_web_y_registra_un_error_claro(?string $secreto): void
    {
        config(['services.revalidate.secret' => $secreto]);
        Http::fake();

        $this->assertFalse($this->revalidar('shipping-returns'));

        Http::assertNothingSent();
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('falta REVALIDATE_SECRET', $this->log[0]);
        $this->assertStringEndsWith(json_encode(['tag' => 'shipping-returns', 'url' => self::URL], JSON_UNESCAPED_SLASHES), $this->log[0]);
    }
}

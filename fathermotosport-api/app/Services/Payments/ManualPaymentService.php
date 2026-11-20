<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\StoreConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Pagos de confirmación manual: transferencia SEPA, transferencia en EE.UU. (ACH / wire
 * a la cuenta Mercury de la LLC), USDT (TRC20 / BEP20), BTC y Binance Pay.
 *
 * El cliente paga por fuera (su banco o su billetera) y una persona confirma el ingreso
 * desde el panel (acción "Confirmar pago recibido" del pedido), que reutiliza markPaid:
 * mismo descuento de stock y mismo correo de confirmación que una pasarela.
 *
 * Los precios de la tienda están en USD. Al crear el pedido se fija el monto exacto en
 * la moneda del método con una cotización en vivo (nunca una tasa escrita en el código):
 *   - EUR: tasa de referencia del BCE (Frankfurter); respaldo open.er-api.com.
 *   - USD (transferencia en EE.UU.): el mismo precio de la tienda, sin conversión.
 *   - USDT: 1 USDT = 1 USD (stablecoin atada al dólar).
 *   - BTC: precio spot (CoinGecko); respaldo Kraken.
 * Si no se puede cotizar, el pedido no se crea: mejor un error claro que un monto falso.
 */
class ManualPaymentService
{
    /**
     * Métodos, su moneda, cuántas horas vale la cotización / el pedido sin pagar, y los
     * campos del panel que tienen que estar completos para ofrecerlos.
     *
     * BTC tiene la ventana más corta porque su precio se mueve: pasado ese plazo el
     * pedido se cancela y el cliente vuelve a pedir con un precio nuevo.
     */
    public const METODOS = [
        'sepa' => ['moneda' => 'EUR', 'horas' => 72, 'campos' => ['sepa_beneficiary', 'sepa_iban']],
        // ACH tarda 1 a 3 días hábiles; un wire llega en el día. 96 h cubren un fin de semana.
        'us_bank' => ['moneda' => 'USD', 'horas' => 96, 'campos' => ['usbank_beneficiary', 'usbank_account_number', 'usbank_routing_number', 'usbank_bank_name']],
        'usdt_trc20' => ['moneda' => 'USDT', 'horas' => 24, 'campos' => ['usdt_trc20_address']],
        'usdt_bep20' => ['moneda' => 'USDT', 'horas' => 24, 'campos' => ['usdt_bep20_address']],
        'btc' => ['moneda' => 'BTC', 'horas' => 2, 'campos' => ['btc_address']],
        // BTC como token en BNB Smart Chain (BTCB), a una dirección 0x… de BSC.
        'btc_bep20' => ['moneda' => 'BTC', 'horas' => 2, 'campos' => ['btc_bep20_address']],
        // Binance Pay: el cliente escanea el QR de cobro desde la app de Binance y paga sin
        // elegir red. El monto se pide en USDT (1:1 con USD); puede pagar el equivalente
        // en USDC o BTC dentro de Binance y el equipo lo verifica al confirmar.
        'binance_pay' => ['moneda' => 'USDT', 'horas' => 24, 'campos' => ['binance_pay_url']],
    ];

    /** Nombre de cada red tal como lo muestran los exchanges al retirar. */
    public const REDES = [
        'usdt_trc20' => 'TRON (TRC20)',
        'usdt_bep20' => 'BNB Smart Chain (BEP20)',
        'btc' => 'Bitcoin',
        'btc_bep20' => 'BNB Smart Chain (BEP20)',
        'binance_pay' => 'Binance Pay',
    ];

    private const CACHE_SEGUNDOS = 300;

    public static function esManual(?string $metodo): bool
    {
        return $metodo !== null && array_key_exists($metodo, self::METODOS);
    }

    /** @return array<string, bool> Método → ¿está configurado en el panel? */
    public function disponibles(?StoreConfig $config = null): array
    {
        $config ??= StoreConfig::current();

        return collect(self::METODOS)->map(fn (array $m) => $config !== null && collect($m['campos'])
            ->every(fn (string $campo) => filled($config->getAttribute($campo)))
        )->all();
    }

    /**
     * Cotización para un método: cuántos USD vale 1 unidad de su moneda.
     *
     * @return array{moneda: string, tasa: float}
     *
     * @throws ValidationException si el método no está configurado o no hay cotización.
     */
    public function cotizar(string $metodo): array
    {
        if (! ($this->disponibles()[$metodo] ?? false)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Este método de pago no está disponible en este momento.'],
            ]);
        }

        $moneda = self::METODOS[$metodo]['moneda'];

        $tasa = match ($moneda) {
            'USDT', 'USD' => 1.0,
            'EUR' => $this->usdPorEuro(),
            'BTC' => $this->usdPorBitcoin(),
        };

        if ($tasa === null) {
            throw ValidationException::withMessages([
                'payment_method' => ['No pudimos obtener la cotización en este momento. Intenta de nuevo en unos minutos o elige otro método de pago.'],
            ]);
        }

        return ['moneda' => $moneda, 'tasa' => $tasa];
    }

    /**
     * Monto a pagar en la moneda del método. EUR y USDT a 2 decimales; BTC a 8 y
     * redondeado hacia arriba, para que el redondeo nunca deje el pago corto.
     */
    public static function montoAPagar(float $totalUsd, array $cotizacion): string
    {
        $monto = $totalUsd / $cotizacion['tasa'];

        return $cotizacion['moneda'] === 'BTC'
            ? number_format(ceil(round($monto * 1e8, 4)) / 1e8, 8, '.', '')
            : number_format(round($monto, 2), 2, '.', '');
    }

    /** Campos del pedido con la cotización fijada. */
    public static function camposDelPedido(string $metodo, float $totalUsd, array $cotizacion): array
    {
        return [
            'payment_currency' => $cotizacion['moneda'],
            'payment_amount' => self::montoAPagar($totalUsd, $cotizacion),
            'payment_rate' => $cotizacion['tasa'],
            'payment_expires_at' => now()->addHours(self::METODOS[$metodo]['horas']),
        ];
    }

    /**
     * Instrucciones de pago para el comprador. Solo se exponen en la respuesta del
     * pedido, que ya está restringida a quien puede verlo (token o dueño).
     */
    public function instrucciones(Order $order, ?StoreConfig $config = null): ?array
    {
        if (! self::esManual($order->payment_method) || $order->payment_amount === null) {
            return null;
        }

        $config ??= StoreConfig::current();
        $metodo = $order->payment_method;

        $base = [
            'method' => $metodo,
            'currency' => $order->payment_currency,
            'amount' => self::formatoMonto($order->payment_amount, $order->payment_currency),
            'rate_usd' => $order->payment_rate !== null ? (string) $order->payment_rate : null,
            'reference' => $order->order_number,
            'expires_at' => $order->payment_expires_at?->toIso8601String(),
        ];

        if ($metodo === 'sepa') {
            return $base + [
                'beneficiary' => $config?->sepa_beneficiary,
                'iban' => self::ibanAgrupado($config?->sepa_iban),
                'bic' => $config?->sepa_bic,
                'bank_name' => $config?->sepa_bank_name,
            ];
        }

        if ($metodo === 'us_bank') {
            return $base + [
                'beneficiary' => $config?->usbank_beneficiary,
                'beneficiary_address' => $config?->usbank_beneficiary_address,
                'bank_name' => $config?->usbank_bank_name,
                'bank_address' => $config?->usbank_bank_address,
                'account_number' => $config?->usbank_account_number,
                'routing_number' => $config?->usbank_routing_number,
                'account_type' => $config?->usbank_account_type ?: 'Checking',
                'swift' => $config?->usbank_swift,
            ];
        }

        if ($metodo === 'binance_pay') {
            return $base + [
                'network' => self::REDES[$metodo],
                'pay_url' => $config?->binance_pay_url,
                'merchant_name' => $config?->binance_pay_name,
                'accepted_currencies' => ['USDT', 'USDC', 'BTC'],
            ];
        }

        $campo = self::METODOS[$metodo]['campos'][0];

        return $base + [
            'network' => self::REDES[$metodo],
            'address' => $config?->getAttribute($campo),
        ];
    }

    public static function formatoMonto(mixed $monto, ?string $moneda): string
    {
        return number_format((float) $monto, $moneda === 'BTC' ? 8 : 2, '.', '');
    }

    // ───────────────────────── Cotizaciones ─────────────────────────

    /** USD por 1 EUR. */
    private function usdPorEuro(): ?float
    {
        return $this->cotizacionCacheada('fx_usd_por_eur', function () {
            // Frankfurter publica las tasas de referencia del BCE.
            $eurPorUsd = $this->leer('https://api.frankfurter.app/latest?from=USD&to=EUR', 'rates.EUR')
                ?? $this->leer('https://open.er-api.com/v6/latest/USD', 'rates.EUR');

            // Un EUR/USD fuera de este rango es una respuesta rota, no el mercado.
            return $eurPorUsd !== null && $eurPorUsd > 0.5 && $eurPorUsd < 2 ? round(1 / $eurPorUsd, 8) : null;
        });
    }

    /** USD por 1 BTC. */
    private function usdPorBitcoin(): ?float
    {
        return $this->cotizacionCacheada('fx_usd_por_btc', function () {
            $precio = $this->leer('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd', 'bitcoin.usd')
                ?? $this->leer('https://api.kraken.com/0/public/Ticker?pair=XBTUSD', 'result.XXBTZUSD.c.0');

            return $precio !== null && $precio > 1000 ? round($precio, 2) : null;
        });
    }

    /** Solo se cachean las cotizaciones válidas: un fallo se reintenta en el próximo pedido. */
    private function cotizacionCacheada(string $clave, callable $obtener): ?float
    {
        $cacheada = Cache::get($clave);
        if (is_numeric($cacheada) && $cacheada > 0) {
            return (float) $cacheada;
        }

        $valor = $obtener();
        if ($valor !== null) {
            Cache::put($clave, $valor, self::CACHE_SEGUNDOS);
        }

        return $valor;
    }

    private function leer(string $url, string $ruta): ?float
    {
        try {
            $respuesta = Http::timeout(6)->acceptJson()->get($url);
            $valor = $respuesta->successful() ? data_get($respuesta->json(), $ruta) : null;

            if (is_numeric($valor) && (float) $valor > 0) {
                return (float) $valor;
            }

            Log::warning('Cotización sin dato válido.', ['url' => $url, 'status' => $respuesta->status()]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo consultar la cotización.', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    // ───────────────────────── Validación de datos del panel ─────────────────────────

    public static function normalizarIban(?string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $iban));
    }

    /** IBAN con el dígito de control correcto (mod 97, ISO 13616). */
    public static function ibanValido(?string $iban): bool
    {
        $iban = self::normalizarIban($iban);
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $reordenado = substr($iban, 4).substr($iban, 0, 4);
        $numerico = preg_replace_callback('/[A-Z]/', fn ($m) => (string) (ord($m[0]) - 55), $reordenado);

        $resto = 0;
        foreach (str_split($numerico, 7) as $bloque) {
            $resto = (int) ($resto.$bloque) % 97;
        }

        return $resto === 1;
    }

    public static function ibanAgrupado(?string $iban): ?string
    {
        $iban = self::normalizarIban($iban);

        return $iban === '' ? null : trim(chunk_split($iban, 4, ' '));
    }

    /** Número de ruta ABA: 9 dígitos con dígito verificador (pesos 3-7-1). */
    public static function routingValido(?string $routing): bool
    {
        $r = preg_replace('/\s+/', '', (string) $routing);
        if (! preg_match('/^\d{9}$/', $r)) {
            return false;
        }

        $pesos = [3, 7, 1, 3, 7, 1, 3, 7, 1];
        $suma = 0;
        foreach (str_split($r) as $i => $d) {
            $suma += (int) $d * $pesos[$i];
        }

        return $suma % 10 === 0;
    }

    /** Número de cuenta en EE.UU.: solo dígitos, de 4 a 17. */
    public static function cuentaUsValida(?string $cuenta): bool
    {
        return (bool) preg_match('/^\d{4,17}$/', preg_replace('/\s+/', '', (string) $cuenta));
    }

    public static function bicValido(?string $bic): bool
    {
        return (bool) preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(trim((string) $bic)));
    }

    public static function direccionValida(string $metodo, ?string $direccion): bool
    {
        $direccion = trim((string) $direccion);

        return match ($metodo) {
            // Base58, empieza con T, 34 caracteres.
            'usdt_trc20' => (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $direccion),
            // Dirección EVM.
            'usdt_bep20', 'btc_bep20' => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $direccion),
            // Enlace de cobro de Binance Pay (el contenido del QR). Solo dominios de Binance.
            'binance_pay' => (bool) preg_match('#^https://(app|s|www)\.binance\.com/[A-Za-z0-9/_\-]+$#', $direccion),
            // Legacy (1…), P2SH (3…) o bech32 / taproot (bc1…).
            'btc' => (bool) preg_match('/^(bc1[ac-hj-np-z02-9]{11,71}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/', $direccion),
            default => false,
        };
    }
}

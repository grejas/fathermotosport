<?php

namespace App\Models;

use App\Services\FrontendRevalidator;
use App\Services\Payments\ManualPaymentService;
use App\Services\Payments\MercadoPagoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración de la tienda (fila única, id=1). Las credenciales de pago no viven
 * acá: se configuran solo en el .env del servidor. Sí viven acá los datos donde el
 * cliente paga por transferencia o cripto (IBAN, direcciones de billetera): no son
 * secretos, se le muestran a cada comprador.
 */
class StoreConfig extends Model
{
    /** Tag de caché compartido con la web (lib/api/storeSettings.ts). */
    public const REVALIDATE_TAG = 'store-settings';

    public const CACHE_KEY = 'store_config_public';

    /** Dominios que acepta cada red (más su variante www.). */
    public const SOCIAL_DOMAINS = [
        'facebook' => ['facebook.com', 'fb.com', 'm.facebook.com'],
        'instagram' => ['instagram.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'tiktok' => ['tiktok.com'],
    ];

    protected $table = 'store_config';

    protected $fillable = [
        'store_name',
        'logo_url',
        'favicon_url',
        'phone',
        'email',
        'currency',
        'whatsapp',
        'facebook',
        'instagram',
        'youtube',
        'tiktok',
        'maintenance_mode',
        'sepa_beneficiary',
        'sepa_iban',
        'sepa_bic',
        'sepa_bank_name',
        'usdt_trc20_address',
        'usdt_bep20_address',
        'btc_address',
        'btc_bep20_address',
        'binance_pay_url',
        'binance_pay_name',
        'mp_link_url',
        'mp_link_name',
        'mp_link_fee_percent',
        'pp_manual_email',
        'pp_manual_link',
        'pp_manual_name',
        'pp_manual_fee_percent',
        'usbank_beneficiary',
        'usbank_beneficiary_address',
        'usbank_bank_name',
        'usbank_bank_address',
        'usbank_account_number',
        'usbank_routing_number',
        'usbank_account_type',
        'usbank_swift',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'mp_link_fee_percent' => 'float',
        'pp_manual_fee_percent' => 'float',
    ];

    /** Si la web confirmó la revalidación del último guardado (null: todavía no se guardó). */
    public ?bool $webRevalidada = null;

    protected static function booted(): void
    {
        // Cualquier guardado (panel, tinker, seeder) invalida la copia del endpoint y
        // avisa a la web, que cachea estos datos 10 minutos.
        static::saved(function (StoreConfig $config) {
            Cache::forget(self::CACHE_KEY);
            $config->webRevalidada = app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG);
        });
    }

    /**
     * Devuelve la configuración única de la tienda (singleton).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /** El WhatsApp se guarda solo con dígitos, venga de donde venga. */
    protected function whatsapp(): Attribute
    {
        return Attribute::set(fn (?string $value) => self::soloDigitos($value));
    }

    public static function soloDigitos(?string $value): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $value);

        return $digitos === '' ? null : $digitos;
    }

    /** Entre 8 y 15 dígitos, con o sin + y espacios. */
    public static function whatsappValido(?string $value): bool
    {
        $value = trim((string) $value);

        return (bool) preg_match('/^\+?[\d ]+$/', $value)
            && preg_match('/^\d{8,15}$/', (string) self::soloDigitos($value));
    }

    /** Solo https y solo los dominios de esa red; nada de usuario, contraseña ni puerto. */
    public static function urlDeRedValida(string $red, ?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $partes = parse_url($url);
        if (strtolower($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])) {
            return false;
        }

        $host = strtolower($partes['host'] ?? '');
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return in_array($host, self::SOCIAL_DOMAINS[$red] ?? [], true);
    }

    /**
     * Lo único que ve la web (GET /api/v1/store/settings). Lista cerrada: nunca sale
     * otro campo de store_config. Vacío => null; una red con un enlace que no pasa la
     * validación también llega como null, para que la web no la muestre.
     *
     * @return array{store_name: ?string, email: ?string, phone: ?string, whatsapp: ?string, social: array<string, ?string>, payment_methods: array<string, bool>, gateways: array<string, bool>}
     */
    public static function datosPublicos(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $config = static::current();
            $texto = fn (?string $value) => trim((string) $value) === '' ? null : trim((string) $value);

            $social = [];
            foreach (array_keys(self::SOCIAL_DOMAINS) as $red) {
                $url = $texto($config?->getAttribute($red));
                $social[$red] = $url !== null && self::urlDeRedValida($red, $url) ? $url : null;
            }

            return [
                'store_name' => $texto($config?->store_name),
                'email' => $texto($config?->email),
                'phone' => $texto($config?->phone),
                'whatsapp' => self::soloDigitos($config?->whatsapp),
                'social' => $social,
                // Solo si cada método está configurado; los datos para pagar no salen
                // acá, van en el pedido (ManualPaymentService::instrucciones).
                'payment_methods' => app(ManualPaymentService::class)->disponibles($config),
                // Pasarelas que dependen de credenciales del .env (no del panel).
                'gateways' => ['mercadopago' => MercadoPagoService::configurado()],
            ];
        });
    }
}

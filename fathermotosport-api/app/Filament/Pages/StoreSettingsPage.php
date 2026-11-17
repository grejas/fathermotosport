<?php

namespace App\Filament\Pages;

use App\Models\StoreConfig;
use App\Services\Payments\ManualPaymentService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class StoreSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Configuración de la tienda';

    protected static ?string $title = 'Configuración de la tienda';

    protected static string $view = 'filament.pages.store-settings-page';

    public ?array $data = [];

    /** Configuración general de la tienda: solo Administrador. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $config = StoreConfig::current() ?? new StoreConfig;

        $this->form->fill([
            'store_name' => $config->store_name,
            'phone' => $config->phone,
            'email' => $config->email,
            'logo_url' => $config->logo_url,
            'favicon_url' => $config->favicon_url,
            'currency' => $config->currency ?? 'USD',
            'maintenance_mode' => (bool) $config->maintenance_mode,
            'whatsapp' => $config->whatsapp,
            'facebook' => $config->facebook,
            'instagram' => $config->instagram,
            'youtube' => $config->youtube,
            'tiktok' => $config->tiktok,
            'sepa_beneficiary' => $config->sepa_beneficiary,
            'sepa_iban' => ManualPaymentService::ibanAgrupado($config->sepa_iban),
            'sepa_bic' => $config->sepa_bic,
            'sepa_bank_name' => $config->sepa_bank_name,
            'usdt_trc20_address' => $config->usdt_trc20_address,
            'usdt_bep20_address' => $config->usdt_bep20_address,
            'btc_address' => $config->btc_address,
            'btc_bep20_address' => $config->btc_bep20_address,
            'binance_pay_url' => $config->binance_pay_url,
            'binance_pay_name' => $config->binance_pay_name,
        ]);
    }

    /** Regla con un validador de ManualPaymentService; vacío siempre pasa. */
    private static function regla(callable $valido, string $mensaje): Closure
    {
        return fn (): Closure => function (string $attribute, $value, Closure $fail) use ($valido, $mensaje) {
            if (filled($value) && ! $valido($value)) {
                $fail($mensaje);
            }
        };
    }

    private static function campoDeBilletera(string $metodo, string $campo, string $label, string $placeholder): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($campo)
            ->label($label)
            ->placeholder($placeholder)
            ->maxLength(90)
            ->rules([self::regla(fn ($v) => ManualPaymentService::direccionValida($metodo, $v), 'La dirección no tiene el formato de esa red. Cópiala de nuevo desde tu billetera.')])
            ->helperText('Revisa que sea de la red correcta: un envío a otra red se pierde. Vacío: el método no se ofrece.');
    }

    /** Regla del enlace de una red: solo https y solo sus dominios. */
    private static function reglaDeRed(string $red): Closure
    {
        $dominios = implode(', ', StoreConfig::SOCIAL_DOMAINS[$red]);

        return fn (): Closure => function (string $attribute, $value, Closure $fail) use ($red, $dominios) {
            if (filled($value) && ! StoreConfig::urlDeRedValida($red, $value)) {
                $fail("Usa un enlace https:// de {$dominios}.");
            }
        };
    }

    private static function campoDeRed(string $red, string $label, string $placeholder): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($red)
            ->label($label)
            ->placeholder($placeholder)
            ->maxLength(255)
            ->rules([self::reglaDeRed($red)])
            ->helperText('Vacío: el icono no aparece en la web.');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la tienda')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('store_name')->label('Nombre de la tienda')->required(),
                        Forms\Components\TextInput::make('phone')->label('Teléfono')
                            ->helperText('La web todavía no lo muestra.'),
                        Forms\Components\TextInput::make('email')->label('Email')->email()
                            ->helperText('Correo de contacto de la web y de los correos a clientes.'),
                        Forms\Components\Select::make('currency')->label('Moneda')
                            ->options(['USD' => 'USD', 'BOB' => 'BOB', 'BRL' => 'BRL'])->required()
                            ->helperText('Todavía no tiene efecto en la web.'),
                        Forms\Components\FileUpload::make('logo_url')->label('Logo')->image()->directory('store')->visibility('public')
                            ->helperText('Todavía no tiene efecto en la web.'),
                        Forms\Components\FileUpload::make('favicon_url')->label('Favicon')->image()->directory('store')->visibility('public')
                            ->helperText('Todavía no tiene efecto en la web.'),
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Modo mantenimiento')
                            ->helperText('Todavía no tiene efecto: la tienda sigue en línea aunque esté activado.'),
                    ]),

                Forms\Components\Section::make('Redes sociales')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')
                            ->placeholder('+591 68736384')
                            ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                if (filled($value) && ! StoreConfig::whatsappValido($value)) {
                                    $fail('Entre 8 y 15 dígitos, con código de país (se aceptan + y espacios).');
                                }
                            }])
                            ->helperText('Se guarda solo con dígitos. Vacío: el enlace no aparece.'),
                        self::campoDeRed('facebook', 'Facebook', 'https://facebook.com/fathermotosport'),
                        self::campoDeRed('instagram', 'Instagram', 'https://instagram.com/fathermotosport'),
                        self::campoDeRed('youtube', 'YouTube', 'https://youtube.com/@fathermotosport'),
                        self::campoDeRed('tiktok', 'TikTok', 'https://tiktok.com/@fathermotosport'),
                    ]),

                Forms\Components\Section::make('Transferencia bancaria en Europa (SEPA · EUR)')
                    ->description('El cliente ve estos datos con el monto exacto en EUR y el número de pedido como concepto. Confirmas el ingreso desde el pedido con "Confirmar pago recibido". Sin beneficiario e IBAN, el método no aparece en la web.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('sepa_beneficiary')->label('Titular de la cuenta')->maxLength(255)
                            ->requiredWith('sepa_iban'),
                        Forms\Components\TextInput::make('sepa_iban')->label('IBAN')->maxLength(42)
                            ->placeholder('ES00 0000 0000 0000 0000 0000')
                            ->requiredWith('sepa_beneficiary')
                            ->rules([self::regla(fn ($v) => ManualPaymentService::ibanValido($v), 'El IBAN no es válido (revisa los dígitos de control).')]),
                        Forms\Components\TextInput::make('sepa_bic')->label('BIC / SWIFT')->maxLength(11)
                            ->rules([self::regla(fn ($v) => ManualPaymentService::bicValido($v), 'BIC de 8 u 11 caracteres.')])
                            ->helperText('Opcional dentro de la zona SEPA, pero ayuda a los bancos de fuera.'),
                        Forms\Components\TextInput::make('sepa_bank_name')->label('Banco')->maxLength(255),
                    ]),

                Forms\Components\Section::make('Pagos en cripto (billetera propia)')
                    ->description('Monto en USDT 1:1 con el precio en USD; en BTC se cotiza en vivo y vale 2 horas. Confirmas el ingreso desde el pedido con el hash de la transacción.')
                    ->columns(1)
                    ->schema([
                        self::campoDeBilletera('usdt_trc20', 'usdt_trc20_address', 'USDT · red TRON (TRC20)', 'T…'),
                        self::campoDeBilletera('usdt_bep20', 'usdt_bep20_address', 'USDT · red BNB Smart Chain (BEP20)', '0x…'),
                        self::campoDeBilletera('btc', 'btc_address', 'Bitcoin (BTC · red Bitcoin)', 'bc1…'),
                        self::campoDeBilletera('btc_bep20', 'btc_bep20_address', 'Bitcoin en BNB Smart Chain (BTC · BEP20)', '0x…'),
                        Forms\Components\TextInput::make('binance_pay_url')
                            ->label('Binance Pay · enlace del QR de cobro')
                            ->placeholder('https://app.binance.com/uni-qr/…')
                            ->maxLength(255)
                            ->rules([self::regla(fn ($v) => ManualPaymentService::direccionValida('binance_pay', $v), 'Pega el enlace que contiene tu QR de cobro de Binance (https://app.binance.com/…).')])
                            ->helperText('Lo que lee el QR de "Recibir" de Binance Pay. La web genera el QR con este enlace. Vacío: Binance Pay no se ofrece.'),
                        Forms\Components\TextInput::make('binance_pay_name')
                            ->label('Binance Pay · nombre que ve el cliente')
                            ->placeholder('fathersons Imp LLC')
                            ->maxLength(100)
                            ->helperText('El mismo nombre que muestra Binance al escanear, para que el cliente confirme que paga a la cuenta correcta.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar configuración')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $texto = fn (string $campo) => filled($data[$campo] ?? null) ? trim($data[$campo]) : null;

        // El evento saved del modelo invalida la caché del endpoint y revalida la web.
        $config = StoreConfig::updateOrCreate(['id' => 1], [
            'store_name' => $data['store_name'],
            'phone' => $texto('phone'),
            'email' => $texto('email'),
            'logo_url' => $data['logo_url'] ?? null,
            'favicon_url' => $data['favicon_url'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'maintenance_mode' => $data['maintenance_mode'] ?? false,
            'whatsapp' => $texto('whatsapp'),
            'facebook' => $texto('facebook'),
            'instagram' => $texto('instagram'),
            'youtube' => $texto('youtube'),
            'tiktok' => $texto('tiktok'),
            'sepa_beneficiary' => $texto('sepa_beneficiary'),
            'sepa_iban' => filled($data['sepa_iban'] ?? null) ? ManualPaymentService::normalizarIban($data['sepa_iban']) : null,
            'sepa_bic' => filled($data['sepa_bic'] ?? null) ? strtoupper(trim($data['sepa_bic'])) : null,
            'sepa_bank_name' => $texto('sepa_bank_name'),
            'usdt_trc20_address' => $texto('usdt_trc20_address'),
            'usdt_bep20_address' => $texto('usdt_bep20_address'),
            'btc_address' => $texto('btc_address'),
            'btc_bep20_address' => $texto('btc_bep20_address'),
            'binance_pay_url' => $texto('binance_pay_url'),
            'binance_pay_name' => $texto('binance_pay_name'),
        ]);

        $this->data['whatsapp'] = $config->whatsapp;

        if ($config->webRevalidada) {
            Notification::make()->title('Guardado. El sitio ya muestra los cambios.')->success()->send();

            return;
        }

        Notification::make()
            ->title('Guardado, pero el sitio no confirmó la actualización')
            ->body('Los cambios están guardados y se verán en el sitio en un máximo de 10 minutos.')
            ->warning()
            ->send();
    }
}

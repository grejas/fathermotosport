<?php

namespace App\Filament\Pages;

use App\Models\StoreConfig;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class StoreSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Configuración de la tienda';

    protected static ?string $title = 'Configuración de la tienda';

    protected static string $view = 'filament.pages.store-settings-page';

    public ?array $data = [];

    /** Contiene credenciales de pago: solo Administrador. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $config = StoreConfig::current() ?? new StoreConfig;
        $keys = $config->payment_keys ?? [];

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
            'paypal_mode' => data_get($keys, 'paypal.mode', 'sandbox'),
            'paypal_client_id' => data_get($keys, 'paypal.client_id'),
            'paypal_client_secret' => data_get($keys, 'paypal.secret'),
            'stripe_key' => data_get($keys, 'stripe.public_key'),
            'stripe_secret' => data_get($keys, 'stripe.secret_key'),
            'stripe_webhook_secret' => data_get($keys, 'stripe.webhook_secret'),
            'mercadopago_public_key' => data_get($keys, 'mercadopago.public_key'),
            'mercadopago_access_token' => data_get($keys, 'mercadopago.access_token'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la tienda')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('store_name')->label('Nombre de la tienda')->required(),
                        Forms\Components\TextInput::make('phone')->label('Teléfono'),
                        Forms\Components\TextInput::make('email')->label('Email')->email(),
                        Forms\Components\Select::make('currency')->label('Moneda')
                            ->options(['USD' => 'USD', 'BOB' => 'BOB', 'BRL' => 'BRL'])->required(),
                        Forms\Components\FileUpload::make('logo_url')->label('Logo')->image()->directory('store')->visibility('public'),
                        Forms\Components\FileUpload::make('favicon_url')->label('Favicon')->image()->directory('store')->visibility('public'),
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Modo mantenimiento')
                            ->helperText('⚠️ Al activarlo, la tienda quedará fuera de línea para los clientes.'),
                    ]),

                Forms\Components\Section::make('Redes sociales')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')->placeholder('+59168736384'),
                        Forms\Components\TextInput::make('facebook')->label('Facebook')->url(),
                        Forms\Components\TextInput::make('instagram')->label('Instagram')->url(),
                        Forms\Components\TextInput::make('youtube')->label('YouTube')->url(),
                        Forms\Components\TextInput::make('tiktok')->label('TikTok')->url()->placeholder('https://tiktok.com/@fathermotosport'),
                    ]),

                Forms\Components\Section::make('Credenciales de pago')
                    ->description('Estas credenciales se guardan cifradas en la base de datos.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('paypal_mode')->label('PayPal — Modo')
                            ->options(['sandbox' => 'Sandbox', 'live' => 'Producción'])->default('sandbox'),
                        Forms\Components\TextInput::make('paypal_client_id')->label('PayPal Client ID'),
                        Forms\Components\TextInput::make('paypal_client_secret')->label('PayPal Secret')->password()->revealable(),
                        Forms\Components\TextInput::make('stripe_key')->label('Stripe Public Key'),
                        Forms\Components\TextInput::make('stripe_secret')->label('Stripe Secret')->password()->revealable(),
                        Forms\Components\TextInput::make('stripe_webhook_secret')->label('Stripe Webhook Secret')->password()->revealable(),
                        Forms\Components\TextInput::make('mercadopago_public_key')->label('MercadoPago Public Key'),
                        Forms\Components\TextInput::make('mercadopago_access_token')->label('MercadoPago Access Token')->password()->revealable(),
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

        $paymentKeys = [
            'paypal' => [
                'mode' => $data['paypal_mode'] ?? 'sandbox',
                'client_id' => $data['paypal_client_id'] ?? '',
                'secret' => $data['paypal_client_secret'] ?? '',
            ],
            'stripe' => [
                'public_key' => $data['stripe_key'] ?? '',
                'secret_key' => $data['stripe_secret'] ?? '',
                'webhook_secret' => $data['stripe_webhook_secret'] ?? '',
            ],
            'mercadopago' => [
                'public_key' => $data['mercadopago_public_key'] ?? '',
                'access_token' => $data['mercadopago_access_token'] ?? '',
            ],
        ];

        // 1. Guardar en store_config.
        StoreConfig::updateOrCreate(['id' => 1], [
            'store_name' => $data['store_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'favicon_url' => $data['favicon_url'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'maintenance_mode' => $data['maintenance_mode'] ?? false,
            'whatsapp' => $data['whatsapp'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'youtube' => $data['youtube'] ?? null,
            'tiktok' => $data['tiktok'] ?? null,
            'payment_keys' => $paymentKeys,
        ]);

        // 2. Invalidar el caché de configuración de la tienda.
        //    Las credenciales de pago quedan en store_config.payment_keys; NO se
        //    escribe el .env (hacerlo desde una petición web es lento y bajo
        //    `php artisan serve` (single-thread) provocaba el timeout de 60s).
        Cache::forget('store_config');

        Notification::make()->title('Configuración guardada correctamente')->success()->send();
    }
}

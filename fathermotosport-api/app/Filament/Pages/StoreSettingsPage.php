<?php

namespace App\Filament\Pages;

use App\Models\StoreConfig;
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
        ]);
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

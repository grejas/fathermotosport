<?php

namespace App\Filament\Pages;

use App\Models\ShippingReturnsSetting;
use App\Models\StoreConfig;
use App\Services\FrontendRevalidator;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class ShippingReturnsPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** Tag de caché de la web que agrupa todo lo que lee este endpoint. */
    public const REVALIDATE_TAG = 'shipping-returns';

    private const LOCALE_LABELS = [
        'es' => 'Español',
        'pt' => 'Português',
        'en' => 'English',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Envíos y devoluciones';

    protected static ?string $title = 'Envíos y devoluciones';

    protected static string $view = 'filament.pages.store-settings-page';

    public ?array $data = [];

    /** Solo textos públicos: administradores y empleados. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public function mount(): void
    {
        $setting = ShippingReturnsSetting::current() ?? new ShippingReturnsSetting;

        $state = [
            'damage_report_hours' => $setting->damage_report_hours ?? 48,
            'withdrawal_days' => $setting->withdrawal_days ?? 7,
        ];

        foreach (ShippingReturnsSetting::LOCALES as $locale) {
            foreach (ShippingReturnsSetting::TEXT_FIELDS as $field) {
                $state[$locale][$field] = data_get($setting->{$field}, $locale);
            }
            // Las listas se editan como un punto por línea.
            foreach (ShippingReturnsSetting::LIST_FIELDS as $field) {
                $state[$locale][$field] = implode("\n", (array) data_get($setting->{$field}, $locale, []));
            }
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plazos')
                    ->description('En los textos, escribe {hours} o {days} donde deba aparecer el número: al cambiarlo acá se actualiza en los tres idiomas.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('damage_report_hours')
                            ->label('Horas para reportar un artículo dañado ({hours})')
                            ->numeric()->integer()->minValue(1)->maxValue(720)->required(),
                        Forms\Components\TextInput::make('withdrawal_days')
                            ->label('Días para devolver sin motivo ({days})')
                            ->numeric()->integer()->minValue(1)->maxValue(365)->required(),
                    ]),

                Forms\Components\Tabs::make('Idiomas')
                    ->tabs(array_map(
                        fn (string $locale) => Forms\Components\Tabs\Tab::make(self::LOCALE_LABELS[$locale])
                            ->schema($this->localeSchema($locale)),
                        ShippingReturnsSetting::LOCALES
                    )),

                Forms\Components\Section::make('Contacto')
                    ->description('Se toma de Configuración de la tienda (solo administradores pueden cambiarlo).')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('contact_email')
                            ->label('Correo')
                            ->content(fn () => StoreConfig::current()?->email ?: '—'),
                        Forms\Components\Placeholder::make('contact_whatsapp')
                            ->label('WhatsApp')
                            ->content(fn () => StoreConfig::current()?->whatsapp ?: '—'),
                    ]),
            ])
            ->statePath('data');
    }

    /** Campos de un idioma. Vacío = la web usa su texto por defecto. */
    private function localeSchema(string $locale): array
    {
        $text = fn (string $field, string $label) => Forms\Components\TextInput::make("{$locale}.{$field}")
            ->label($label)->maxLength(255);
        $area = fn (string $field, string $label, int $rows = 3) => Forms\Components\Textarea::make("{$locale}.{$field}")
            ->label($label)->rows($rows);
        $list = fn (string $field, string $label) => Forms\Components\Textarea::make("{$locale}.{$field}")
            ->label($label)->rows(5)->helperText('Un punto por línea.');

        return [
            Forms\Components\Placeholder::make("{$locale}_hint")
                ->hiddenLabel()
                ->content(new HtmlString('<span class="text-sm text-gray-500">Si dejas un campo vacío, el sitio muestra su texto por defecto.</span>')),

            Forms\Components\Fieldset::make('Ficha de producto (acordeón)')
                ->columns(2)
                ->schema([
                    $text('badge_shipping', 'Etiqueta de envío'),
                    $text('badge_returns', 'Etiqueta de devolución'),
                    $area('summary_shipping', 'Resumen: envío', 2)->columnSpanFull(),
                    $area('summary_damaged', 'Resumen: artículo dañado')->columnSpanFull(),
                    $area('summary_withdrawal', 'Resumen: devolución sin motivo')->columnSpanFull(),
                ]),

            Forms\Components\Fieldset::make('Página /returns-policy')
                ->columns(1)
                ->schema([
                    $text('page_title', 'Título de la página'),
                    $area('page_intro', 'Introducción'),
                    $text('damaged_title', 'Sección 1: título (artículo dañado)'),
                    $list('damaged_items', 'Sección 1: puntos'),
                    $text('withdrawal_title', 'Sección 2: título (arrepentimiento)'),
                    $list('withdrawal_items', 'Sección 2: puntos'),
                    $text('process_title', 'Sección 3: título (proceso)'),
                    $list('process_items', 'Sección 3: pasos (numerados)'),
                    $text('cancellations_title', 'Sección 4: título (cancelaciones)'),
                    $list('cancellations_items', 'Sección 4: puntos'),
                    $area('help_text', 'Texto sobre los botones de contacto', 2),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $attributes = [
            'damage_report_hours' => (int) $data['damage_report_hours'],
            'withdrawal_days' => (int) $data['withdrawal_days'],
        ];

        foreach (ShippingReturnsSetting::TEXT_FIELDS as $field) {
            foreach (ShippingReturnsSetting::LOCALES as $locale) {
                $value = trim((string) data_get($data, "{$locale}.{$field}", ''));
                $attributes[$field][$locale] = $value === '' ? null : $value;
            }
        }

        foreach (ShippingReturnsSetting::LIST_FIELDS as $field) {
            foreach (ShippingReturnsSetting::LOCALES as $locale) {
                $lines = preg_split('/\R/', (string) data_get($data, "{$locale}.{$field}", ''));
                $attributes[$field][$locale] = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
            }
        }

        // El evento saved del modelo invalida la caché del endpoint.
        ShippingReturnsSetting::updateOrCreate(['id' => 1], $attributes);

        if (app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG)) {
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

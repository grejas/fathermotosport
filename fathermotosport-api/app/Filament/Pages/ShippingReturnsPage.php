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

        $state = ['damage_report_hours' => $setting->damage_report_hours ?? 48];

        foreach (ShippingReturnsSetting::LOCALES as $locale) {
            foreach ([...ShippingReturnsSetting::TEXT_FIELDS, 'page_body'] as $field) {
                $state[$locale][$field] = data_get($setting->{$field}, $locale);
            }
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plazo')
                    ->description('En cualquier texto, escribe {hours} donde deba aparecer el número de horas: al cambiarlo acá se actualiza en los tres idiomas.')
                    ->schema([
                        Forms\Components\TextInput::make('damage_report_hours')
                            ->label('Horas para reportar un producto defectuoso, dañado o incorrecto ({hours})')
                            ->numeric()->integer()->minValue(1)->maxValue(720)->required(),
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
        return [
            Forms\Components\Placeholder::make("{$locale}_hint")
                ->hiddenLabel()
                ->content(new HtmlString('<span class="text-sm text-gray-500">Si dejas un campo vacío, el sitio muestra su texto por defecto.</span>')),

            Forms\Components\Fieldset::make('Ficha de producto (acordeón)')
                ->columns(1)
                ->schema([
                    Forms\Components\TextInput::make("{$locale}.badge_returns")
                        ->label('Etiqueta de devolución')
                        ->maxLength(255),
                    Forms\Components\Textarea::make("{$locale}.summary")
                        ->label('Resumen')
                        ->rows(4)
                        ->helperText('Deja una línea en blanco para separar párrafos.'),
                ]),

            Forms\Components\Fieldset::make('Página /returns-policy')
                ->columns(1)
                ->schema([
                    Forms\Components\TextInput::make("{$locale}.page_title")
                        ->label('Título de la página')
                        ->maxLength(255),
                    Forms\Components\RichEditor::make("{$locale}.page_body")
                        ->label('Cuerpo de la política')
                        ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                        ->helperText('Debajo del texto, la página siempre muestra los botones de WhatsApp y correo.'),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $attributes = ['damage_report_hours' => (int) $data['damage_report_hours']];

        foreach (ShippingReturnsSetting::LOCALES as $locale) {
            foreach (ShippingReturnsSetting::TEXT_FIELDS as $field) {
                $value = trim((string) data_get($data, "{$locale}.{$field}", ''));
                $attributes[$field][$locale] = $value === '' ? null : $value;
            }

            // Se guarda ya sanitizado: sin scripts ni atributos peligrosos.
            $attributes['page_body'][$locale] = ShippingReturnsSetting::cleanHtml(data_get($data, "{$locale}.page_body"));
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

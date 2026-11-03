<?php

namespace App\Filament\Pages;

use App\Models\BirthdayEmail;
use App\Models\BirthdaySetting;
use App\Services\CumpleanosPrueba;
use App\Services\CumpleanosService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Correo de cumpleaños: porcentaje y días de validez del cupón, interruptor del envío
 * automático (apagado hasta que el administrador lo encienda), vista previa y envío de
 * prueba. El envío lo hace el comando cumpleanos:enviar, cada día a las 10:00 de La Paz.
 */
class CumpleanosPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Clientes';

    protected static ?string $navigationLabel = 'Cumpleaños';

    protected static ?string $title = 'Correo de cumpleaños';

    protected static ?string $slug = 'cumpleanos';

    protected static string $view = 'filament.pages.store-settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $ajustes = BirthdaySetting::actual();

        $this->form->fill([
            'discount_percent' => (float) $ajustes->discount_percent,
            'valid_days' => $ajustes->valid_days,
            'auto_send' => $ajustes->auto_send,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cupón de regalo')
                    ->description('Cupón personal de un solo uso (BDAY-XXXXXX) que solo usa el cliente con su sesión. Se aplica sobre el precio ya rebajado por la categoría y no se combina con otro cupón.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('discount_percent')
                            ->label('Descuento (%)')
                            ->numeric()
                            ->required()
                            ->minValue(BirthdaySetting::PORCENTAJE_MIN)
                            ->maxValue(BirthdaySetting::PORCENTAJE_MAX)
                            ->step(0.01)
                            ->rule('decimal:0,2')
                            ->suffix('%')
                            ->helperText('Entre 0,5 y 90, hasta dos decimales.'),
                        Forms\Components\TextInput::make('valid_days')
                            ->label('Días de validez')
                            ->integer()
                            ->required()
                            ->minValue(BirthdaySetting::DIAS_MIN)
                            ->maxValue(BirthdaySetting::DIAS_MAX)
                            ->helperText('Entre 1 y 90. Vence al final de ese día.'),
                    ]),
                Forms\Components\Section::make('Envío')
                    ->schema([
                        Forms\Components\Toggle::make('auto_send')
                            ->label('Enviar automáticamente cada día a las 10:00')
                            ->helperText('Apagado, no se envía nada. Solo reciben los clientes con correo comprobado y fecha de nacimiento, una vez por año.'),
                        Forms\Components\Placeholder::make('resumen')
                            ->label('Este año')
                            ->content(fn () => $this->resumenDelAnio()),
                    ]),
            ])
            ->statePath('data');
    }

    private function resumenDelAnio(): string
    {
        $conteo = BirthdayEmail::where('year', CumpleanosService::hoy()->year)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return sprintf(
            '%d enviados · %d pendientes · %d fallidos · %d omitidos',
            $conteo[BirthdayEmail::ENVIADO] ?? 0,
            $conteo[BirthdayEmail::PENDIENTE] ?? 0,
            $conteo[BirthdayEmail::FALLIDO] ?? 0,
            $conteo[BirthdayEmail::OMITIDO] ?? 0,
        );
    }

    public function save(): void
    {
        $data = $this->form->getState();

        BirthdaySetting::actual()->update([
            'discount_percent' => round((float) $data['discount_percent'], 2),
            'valid_days' => (int) $data['valid_days'],
            'auto_send' => (bool) ($data['auto_send'] ?? false),
        ]);

        Notification::make()->title('Ajustes de cumpleaños guardados')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $vistaPrevia = fn (string $idioma, string $etiqueta) => Action::make("vista_previa_{$idioma}")
            ->label($etiqueta)
            ->modalHeading("Vista previa del correo ({$idioma})")
            ->modalDescription('Con los ajustes guardados y el código de ejemplo BDAY-PRUEBA.')
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn () => new HtmlString(
                '<iframe title="Vista previa" srcdoc="'.e(app(CumpleanosPrueba::class)->html($idioma)).'" style="width:100%;height:680px;border:0;border-radius:8px;background:#f5f5f5"></iframe>'
            ));

        return [
            ActionGroup::make([
                $vistaPrevia('es', 'Español'),
                $vistaPrevia('pt', 'Português'),
                $vistaPrevia('en', 'English'),
            ])->label('Vista previa')->icon('heroicon-o-eye')->button()->color('gray'),

            Action::make('enviar_prueba')
                ->label('Enviar prueba')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Enviar el correo de cumpleaños de prueba')
                ->modalDescription('Llega con el código de ejemplo BDAY-PRUEBA: no crea cupones ni cuenta como envío del año.')
                ->form([
                    Forms\Components\TextInput::make('email')->label('Correo')->email()->required(),
                    Forms\Components\Select::make('idioma')->label('Idioma')
                        ->options(['es' => 'Español', 'pt' => 'Português', 'en' => 'English'])->default('es')->required(),
                ])
                ->modalSubmitActionLabel('Enviar')
                ->action(function (array $data) {
                    try {
                        app(CumpleanosPrueba::class)->enviar($data['email'], $data['idioma']);
                        Notification::make()->title("Prueba enviada a {$data['email']}")->success()->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('No se pudo enviar la prueba')->body('Revisa storage/logs/laravel.log.')->danger()->send();
                    }
                }),
        ];
    }
}

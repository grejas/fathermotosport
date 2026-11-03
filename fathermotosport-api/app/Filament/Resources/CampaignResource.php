<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Resources\MailCampaignResource\RelationManagers\DestinatariosRelationManager;
use App\Models\MailCampaign;
use App\Models\User;
use App\Services\CampanaManual;
use App\Support\FotoDeResena;
use App\Support\ImagenDeCampana;
use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campañas manuales de correo (remarketing, fase 4). Solo el administrador crea, edita
 * (en borrador), programa, envía, pausa, cancela y duplica; administradores y empleados
 * ven el historial con el detalle por destinatario. El envío lo hace campanas:enviar por
 * tandas, con el cupo de Resend (CampanaManual, EnvioPorTandas).
 */
class CampaignResource extends Resource
{
    protected static ?string $model = MailCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campañas';

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'campañas';

    protected static ?string $slug = 'campanas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', MailCampaign::TIPO_MANUAL);
    }

    public static function form(Form $form): Form
    {
        $paises = User::query()->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country', 'country')->all();

        return $form->schema([
            Forms\Components\Hidden::make('form_token')->default(fn () => (string) Str::uuid())->dehydrated(),

            Forms\Components\Section::make('Campaña')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('Nombre interno')->required()->maxLength(255)
                    ->helperText('Solo lo ve el personal del panel.'),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Fecha y hora de envío (opcional)')
                    ->seconds(false)
                    ->native(false)
                    ->timezone(config('marketing.timezone', 'America/La_Paz'))
                    ->displayFormat('d/m/Y H:i')
                    ->helperText('Hora de Bolivia. Vacío: sale al confirmar el envío.'),
            ]),

            // Destinatarios: por defecto todos los fiables; los filtros son opcionales y plegados.
            Forms\Components\Placeholder::make('destinatarios')->label('Destinatarios')
                ->content('Se envía a todos los clientes con correo comprobado que no se dieron de baja.'),

            Forms\Components\Section::make('Filtros avanzados (opcional)')
                ->description('Sin filtros, se envía a todos.')
                ->collapsible()->collapsed()->persistCollapsed(false)
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('payload.segmento.paises')->label('País')->multiple()->options($paises)
                        ->placeholder('Todos'),
                    Forms\Components\Select::make('payload.segmento.idiomas')->label('Idioma del cliente')->multiple()
                        ->options(CampanaManual::IDIOMAS)->placeholder('Todos')
                        ->helperText('Filtra por el idioma de la cuenta; el correo sale en español igual.'),
                    Forms\Components\Toggle::make('payload.segmento.solo_compradores')->label('Solo clientes con compra pagada')->inline(false),
                ]),

            // Contenido: solo en español (CampanaManual::IDIOMAS_ACTIVOS). Para reactivar otro
            // idioma: sumarlo ahí y repetir camposDeContenido() para él en una pestaña.
            Forms\Components\Section::make('Contenido (en español)')->schema([
                ...self::camposDeContenido('es'),
                Forms\Components\FileUpload::make('payload.imagen')
                    ->label('Imagen (opcional)')
                    ->image()
                    ->disk('public')
                    ->directory(ImagenDeCampana::CARPETA)
                    ->visibility('public')
                    ->acceptedFileTypes(ImagenDeCampana::TIPOS)
                    ->maxSize(ImagenDeCampana::MAX_KB)
                    // Tipo real y píxeles (mimetypes y tamaño los ponen acceptedFileTypes y maxSize).
                    ->rules([fn () => FotoDeResena::reglaDeDimensiones()])
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => ImagenDeCampana::guardar($file))
                    ->live()
                    ->helperText('JPG, PNG o WebP de hasta 5 MB. Se vuelve a codificar (sin datos ocultos) y se adapta a 600 px.'),
                Forms\Components\TextInput::make('payload.contenido.es.imagen_alt')->label('Texto alternativo de la imagen')->maxLength(150)
                    ->live(debounce: 600)
                    ->helperText('Obligatorio si hay imagen: describe lo que se ve.'),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('payload.contenido.es.boton_texto')->label('Texto del botón (opcional)')->maxLength(60)
                        ->live(debounce: 600),
                    Forms\Components\TextInput::make('payload.contenido.es.boton_url')->label('Enlace del botón')->url()->maxLength(500)
                        ->live(onBlur: true)
                        ->rule('starts_with:https://')->validationMessages(['starts_with' => 'El enlace tiene que empezar con https://.']),
                ]),
            ]),

            // Vista previa en vivo: se arma con lo que hay en el formulario, sin guardar ni
            // enviar, para un cliente de ejemplo. No cuenta en el cupo.
            Forms\Components\Section::make('Vista previa')->schema([
                Forms\Components\ToggleButtons::make('vista_ancho')->label('Ancho')
                    ->options(['600' => 'Escritorio (600 px)', '375' => 'Móvil (375 px)'])
                    ->default('600')->inline()->live()->dehydrated(false),
                Forms\Components\Placeholder::make('vista_previa')->hiddenLabel()
                    ->content(fn (Get $get) => self::vistaEnVivo((array) ($get('payload') ?? []), (string) ($get('vista_ancho') ?: '600'))),
            ]),
        ]);
    }

    /** Asunto, preheader, titular y texto de un idioma, con actualización en vivo. */
    private static function camposDeContenido(string $i): array
    {
        return [
            Forms\Components\TextInput::make("payload.contenido.{$i}.asunto")->label('Asunto')->maxLength(150)->required($i === 'es')
                ->live(debounce: 600),
            Forms\Components\TextInput::make("payload.contenido.{$i}.preheader")->label('Texto de vista previa (preheader)')->maxLength(200)
                ->live(debounce: 600)
                ->helperText('Se ve junto al asunto en la bandeja de entrada.'),
            Forms\Components\TextInput::make("payload.contenido.{$i}.titular")->label('Titular')->maxLength(150)->required($i === 'es')
                ->live(debounce: 600),
            Forms\Components\RichEditor::make("payload.contenido.{$i}.cuerpo")->label('Texto')
                ->toolbarButtons(['bold', 'italic', 'link', 'undo', 'redo'])
                ->required($i === 'es')
                ->live(onBlur: true)
                ->helperText('Párrafos con negrita, cursiva y enlaces https. Cualquier otro formato se quita.'),
        ];
    }

    /**
     * Payload listo para la vista previa o la prueba desde el estado del formulario (sin
     * guardar). Una imagen recién subida todavía no tiene ruta: va su URL temporal.
     *
     * @return array{0: array, 1: ?string} payload e imagen temporal
     */
    public static function payloadDelFormulario(array $payload): array
    {
        $imagen = $payload['imagen'] ?? null;
        if (is_array($imagen)) {
            $imagen = array_values($imagen)[0] ?? null;
        }

        $imagenUrl = null;
        if ($imagen instanceof TemporaryUploadedFile) {
            try {
                $imagenUrl = $imagen->isPreviewable() ? $imagen->temporaryUrl() : null;
            } catch (\Throwable) {
                $imagenUrl = null;
            }
            $imagen = null;
        }
        $payload['imagen'] = is_string($imagen) && $imagen !== '' ? $imagen : null;

        return [$payload, $imagenUrl];
    }

    /**
     * "Enviar prueba" en el formulario (crear y editar): manda lo que está escrito, sin
     * guardar, a un correo escrito a mano. Mismo correo que la vista previa; no cuenta en
     * el cupo ni crea destinatarios.
     *
     * @param  Closure(): array  $payload  payload del formulario (estado sin validar)
     */
    public static function accionPruebaDelFormulario(Closure $payload): Actions\Action
    {
        return Actions\Action::make('enviar_prueba')->label('Enviar prueba')->icon('heroicon-o-paper-airplane')->color('gray')
            ->visible(fn () => (bool) auth()->user()?->isAdmin())
            ->modalHeading('Enviar una prueba de lo que está escrito')
            ->modalDescription('Solo a este correo, sin guardar la campaña: no cuenta en el cupo ni queda como destinatario.')
            ->form([Forms\Components\TextInput::make('email')->label('Correo')->email()->required()])
            ->modalSubmitActionLabel('Enviar')
            ->action(function (array $data) use ($payload) {
                [$contenido, $imagenUrl] = self::payloadDelFormulario($payload());
                $correo = CampanaManual::correoDeEjemplo($contenido, 'es', $imagenUrl);
                if (! $correo->contenido()) {
                    Notification::make()->title('Falta contenido')->body('Escribe el asunto, el titular y el texto antes de probar.')->warning()->send();

                    return;
                }

                try {
                    Mail::to($data['email'])->send($correo);
                    Notification::make()->title("Prueba enviada a {$data['email']}")->success()->send();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('No se pudo enviar la prueba')->body('Revisa storage/logs/laravel.log.')->danger()->send();
                }
            });
    }

    private static function vistaEnVivo(array $payload, string $ancho): HtmlString
    {
        [$payload, $imagenUrl] = self::payloadDelFormulario($payload);
        $correo = CampanaManual::correoDeEjemplo($payload, 'es', $imagenUrl);
        $contenido = $correo->contenido();

        return new HtmlString(view('filament.campanas.vista-en-vivo', [
            'html' => $contenido ? $correo->render() : null,
            'asunto' => (string) ($contenido['asunto'] ?? ($payload['contenido']['es']['asunto'] ?? '')),
            'preheader' => (string) ($payload['contenido']['es']['preheader'] ?? ''),
            // Móvil: pantalla de 375. Escritorio: el correo a sus 600 px más el margen gris.
            'ancho' => $ancho === '375' ? 375 : 640,
        ])->render());
    }

    /** Mismo detalle que los avisos de descuento (MailCampaignResource). */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(MailCampaignResource::esquemaDeDetalle());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(MailCampaignResource::columnasDeLista())
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options(MailCampaign::ESTADOS),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('Detalle')])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [DestinatariosRelationManager::class];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin() && $record instanceof MailCampaign && $record->editable();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/crear'),
            'view' => Pages\ViewCampaign::route('/{record}'),
            'edit' => Pages\EditCampaign::route('/{record}/editar'),
        ];
    }
}

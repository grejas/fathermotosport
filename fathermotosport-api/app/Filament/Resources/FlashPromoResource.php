<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FlashPromoResource\Pages;
use App\Models\Category;
use App\Models\FlashPromo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FlashPromoResource extends Resource
{
    protected static ?string $model = FlashPromo::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Promociones Flash';

    protected static ?string $modelLabel = 'Promoción flash';

    protected static ?string $pluralModelLabel = 'Promociones flash';

    /** Zona horaria local del negocio (UTC-4). El picker muestra/acepta hora local y guarda en UTC. */
    public const TIMEZONE = 'America/La_Paz';

    /** Valor centinela del selector para "toda la tienda" (no es una categoría real). */
    public const ALL_OPTION = 'all';

    /** Margen de tolerancia (min) para "starts_at no puede ser pasado": permite now() - 5 min. */
    public const START_TOLERANCE_MINUTES = 5;

    /** Multiplicadores de cada unidad a minutos (para el descanso entre repeticiones). */
    private const UNIT_MINUTES = [
        'minutes' => 1,
        'hours' => 60,
        'days' => 1440,
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Promoción programada')
                ->description('Cada promoción se muestra en el sitio solo dentro de su ventana de fecha/hora, si está activa. Las horas son de Bolivia (UTC-4).')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Inicio')
                        ->required()
                        ->seconds(false)
                        ->native(false)
                        ->timezone(self::TIMEZONE)
                        ->displayFormat('d/m/Y H:i')
                        // BUG 2: prellena con la hora local actual al crear.
                        ->default(now())
                        // BUG 1 + BUG 3: bloquea en el calendario las fechas/horas pasadas y las
                        // valida en servidor (solo al crear; al editar una promo en curso/pasada no
                        // se bloquea). minDate convierte según el timezone del picker, evitando el
                        // desfase UTC↔local. Margen de tolerancia de 5 min: si el admin elige "ahora"
                        // y tarda un poco en guardar, no lo rechaza.
                        ->minDate(fn (string $operation) => $operation === 'create'
                            ? now(self::TIMEZONE)->subMinutes(self::START_TOLERANCE_MINUTES)
                            : null)
                        ->validationMessages([
                            'after_or_equal' => 'La fecha de inicio no puede ser en el pasado.',
                        ]),

                    // Duración: valor + unidad (form-only). ends_at = starts_at + duración se
                    // calcula en las páginas Crear/Editar. Reemplaza al antiguo DateTimePicker de fin.
                    Forms\Components\Group::make()
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('duration_value')
                                ->label('Duración')
                                ->numeric()
                                ->minValue(1)
                                ->default(60)
                                ->required()
                                ->dehydrated(false)
                                ->helperText('Cuánto dura activa la promo desde el inicio.'),
                            Forms\Components\Select::make('duration_unit')
                                ->label('Unidad')
                                ->options(['minutes' => 'Minutos', 'hours' => 'Horas', 'days' => 'Días'])
                                ->default('minutes')
                                ->selectablePlaceholder(false)
                                ->required()
                                ->dehydrated(false),
                        ]),

                    Forms\Components\Textarea::make('promo_text')
                        ->label('Texto de la promoción')
                        ->placeholder('Ej: Llévate una visera gratis con cualquier casco')
                        ->rows(2)
                        ->maxLength(500)
                        ->required()
                        ->columnSpanFull(),

                    // "Todas las categorías" es una opción MÁS del selector (valor centinela
                    // self::ALL_OPTION). No es una categoría real: al guardar equivale a "sin
                    // categorías" = toda la tienda. La sincronización real del pivote se hace en
                    // las páginas Crear/Editar (este campo es dehydrated(false)).
                    Forms\Components\Select::make('category_ids')
                        ->label('Aplica a')
                        ->options(fn (): array => [self::ALL_OPTION => 'Todas las categorías']
                            + Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->default([self::ALL_OPTION])
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->helperText('Elige «Todas las categorías» para toda la tienda, o una/varias categorías específicas.')
                        ->validationMessages([
                            'required' => 'Elige «Todas las categorías» o al menos una categoría específica.',
                        ])
                        // "Todas" es mutuamente excluyente con las categorías específicas.
                        ->afterStateUpdated(function ($state, $old, Set $set): void {
                            $state = (array) $state;
                            $old = (array) $old;
                            $addedAll = in_array(self::ALL_OPTION, $state, true) && ! in_array(self::ALL_OPTION, $old, true);

                            if ($addedAll) {
                                // Se acaba de elegir "Todas": excluye al resto.
                                $set('category_ids', [self::ALL_OPTION]);
                            } elseif (in_array(self::ALL_OPTION, $state, true) && count($state) > 1) {
                                // Se agregó una específica teniendo "Todas": quita "Todas".
                                $set('category_ids', array_values(array_filter($state, fn ($v) => $v !== self::ALL_OPTION)));
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->helperText('Desactívala para ocultarla sin borrarla.'),

                    Forms\Components\Toggle::make('is_recurring')
                        ->label('Repetir automáticamente')
                        ->default(false)
                        ->live()
                        ->helperText('Si está activo, al terminar cada ocurrencia el sistema crea la siguiente sola (activa X tiempo, descansa Y tiempo, repite).')
                        ->columnSpanFull(),

                    // Descanso entre repeticiones: valor + unidad (form-only). Se convierte a
                    // rest_minutes en las páginas Crear/Editar (campos dehydrated(false)).
                    Forms\Components\Group::make()
                        ->columns(2)
                        ->visible(fn (Get $get): bool => (bool) $get('is_recurring'))
                        ->schema([
                            Forms\Components\TextInput::make('rest_value')
                                ->label('Descanso entre repeticiones')
                                ->numeric()
                                ->minValue(0)
                                ->default(60)
                                ->required(fn (Get $get): bool => (bool) $get('is_recurring'))
                                ->dehydrated(false)
                                ->helperText('Tiempo de pausa entre una repetición y la siguiente.'),
                            Forms\Components\Select::make('rest_unit')
                                ->label('Unidad')
                                ->options(['minutes' => 'Minutos', 'hours' => 'Horas', 'days' => 'Días'])
                                ->default('minutes')
                                ->selectablePlaceholder(false)
                                ->required(fn (Get $get): bool => (bool) $get('is_recurring'))
                                ->dehydrated(false),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (FlashPromo $r): string => static::computeStatus($r))
                    ->color(fn (string $state): string => match ($state) {
                        'Activa ahora' => 'success',
                        'Programada' => 'info',
                        'Finalizada' => 'gray',
                        'Inactiva' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('promo_text')->label('Texto')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('categories.name')->label('Categorías')->badge()->placeholder('Todas la tienda'),
                Tables\Columns\TextColumn::make('starts_at')->label('Inicio')->dateTime('d/m/Y H:i', self::TIMEZONE)->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->label('Fin')->dateTime('d/m/Y H:i', self::TIMEZONE)->sortable(),
                Tables\Columns\TextColumn::make('is_recurring')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '🔁 Recurrente' : 'Única')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
                Tables\Filters\TernaryFilter::make('is_recurring')->label('Recurrente'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    /** Etiqueta de estado calculada para el listado. */
    protected static function computeStatus(FlashPromo $promo): string
    {
        if (! $promo->is_active) {
            return 'Inactiva';
        }

        $now = Carbon::now();

        if ($promo->starts_at->gt($now)) {
            return 'Programada';
        }

        if ($promo->ends_at->lt($now)) {
            return 'Finalizada';
        }

        return 'Activa ahora';
    }

    /**
     * Sincroniza el pivote de categorías desde el estado del selector.
     * "Todas las categorías" (centinela) o vacío => sin categorías = toda la tienda.
     *
     * @param  mixed  $state  Valor crudo del multi-select category_ids.
     */
    public static function syncCategoriesFromState(FlashPromo $promo, mixed $state): void
    {
        $state = (array) $state;

        // "Todas" tiene prioridad: si está presente (o no hay selección), sin categorías.
        if ($state === [] || in_array(self::ALL_OPTION, $state, true)) {
            $promo->categories()->sync([]);

            return;
        }

        $ids = array_values(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (int) $v : null, $state),
            fn ($v) => $v !== null
        ));

        $promo->categories()->sync($ids);
    }

    /**
     * Calcula rest_minutes desde los campos valor+unidad del formulario, según el
     * estado de "Repetir automáticamente". Si no es recurrente, rest_minutes = null.
     *
     * @param  array<string, mixed>  $data       Datos deshidratados (incluye is_recurring).
     * @param  array<string, mixed>  $rawState   Estado crudo del form (incluye rest_value/rest_unit).
     * @return array<string, mixed>
     */
    public static function applyRecurringToData(array $data, array $rawState): array
    {
        if (empty($data['is_recurring'])) {
            $data['rest_minutes'] = null;

            return $data;
        }

        $value = $rawState['rest_value'] ?? 0;
        $unit = $rawState['rest_unit'] ?? 'minutes';
        $multiplier = self::UNIT_MINUTES[$unit] ?? 1;

        $data['rest_minutes'] = max(0, (int) round(((float) $value) * $multiplier));

        return $data;
    }

    /**
     * Descompone rest_minutes en valor+unidad para prellenar el formulario al editar.
     *
     * @return array{0: int, 1: string}
     */
    public static function restMinutesToValueUnit(?int $minutes): array
    {
        return self::minutesToValueUnit((int) ($minutes ?? 60));
    }

    /**
     * Calcula ends_at = starts_at + (duración valor+unidad) y lo escribe en $data.
     *
     * @param  array<string, mixed>  $data       Datos deshidratados (incluye starts_at en UTC).
     * @param  array<string, mixed>  $rawState   Estado crudo del form (incluye duration_value/unit).
     * @return array<string, mixed>
     */
    public static function applyDurationToData(array $data, array $rawState): array
    {
        if (empty($data['starts_at'])) {
            return $data;
        }

        $value = $rawState['duration_value'] ?? 60;
        $unit = $rawState['duration_unit'] ?? 'minutes';
        $multiplier = self::UNIT_MINUTES[$unit] ?? 1;
        $durationMinutes = max(1, (int) round(((float) $value) * $multiplier));

        $data['ends_at'] = Carbon::parse($data['starts_at'])->addMinutes($durationMinutes);

        return $data;
    }

    /**
     * Descompone la duración guardada (ends_at - starts_at) en valor+unidad legible.
     *
     * @return array{0: int, 1: string}
     */
    public static function durationToValueUnit(FlashPromo $promo): array
    {
        $minutes = 60;

        if ($promo->starts_at && $promo->ends_at) {
            $minutes = (int) round(($promo->ends_at->getTimestamp() - $promo->starts_at->getTimestamp()) / 60);
        }

        return self::minutesToValueUnit(max(1, $minutes));
    }

    /**
     * Elige la unidad más grande y limpia (entera) para mostrar unos minutos.
     *
     * @return array{0: int, 1: string}
     */
    private static function minutesToValueUnit(int $minutes): array
    {
        $minutes = max(0, $minutes);

        if ($minutes > 0 && $minutes % self::UNIT_MINUTES['days'] === 0) {
            return [intdiv($minutes, self::UNIT_MINUTES['days']), 'days'];
        }

        if ($minutes > 0 && $minutes % self::UNIT_MINUTES['hours'] === 0) {
            return [intdiv($minutes, self::UNIT_MINUTES['hours']), 'hours'];
        }

        return [$minutes, 'minutes'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('categories');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFlashPromos::route('/'),
            'create' => Pages\CreateFlashPromo::route('/create'),
            'edit' => Pages\EditFlashPromo::route('/{record}/edit'),
        ];
    }
}

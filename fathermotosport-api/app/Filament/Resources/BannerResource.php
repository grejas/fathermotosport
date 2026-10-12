<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Banners';

    protected static ?string $modelLabel = 'Banner';

    protected static ?string $pluralModelLabel = 'Banners';

    /** Tope de cada video, en KB (150 MB). Lo mismo valida Livewire al subir (config/livewire.php). */
    public const MAX_VIDEO_KB = 153600;

    /**
     * Cualquier video (mp4, webm, mov, m4v, avi, mkv, ogv, wmv...). El servidor lo valida
     * por el contenido del archivo, no por la extensión: lo que no es video se rechaza.
     * application/mp4: algunas versiones de libmagic informan así ciertos MP4 reales.
     */
    public const TIPOS_DE_VIDEO = ['video/*', 'application/mp4'];

    /**
     * Para el chequeo previo del navegador: algunos no informan el tipo de estos
     * formatos y FilePond los rechazaría antes de subirlos.
     */
    private const MIME_POR_EXTENSION = [
        'mkv' => 'video/x-matroska',
        'ogv' => 'video/ogg',
        'm4v' => 'video/x-m4v',
        'wmv' => 'video/x-ms-wmv',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
    ];

    public static function form(Form $form): Form
    {
        $esVideo = fn (Get $get): bool => $get('media_type') === 'video';

        return $form->schema([
            Forms\Components\Section::make('Contenido')
                ->description("El hero conserva sus textos; el título y el texto solo se usan en 'Debajo de categorías'. Se cargan en español; la web los traduce sola al portugués y al inglés.")
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Título (opcional)')
                        ->helperText("Solo se muestra en 'Debajo de categorías'.")
                        ->maxLength(255),
                    Forms\Components\TextInput::make('button_text')
                        ->label('Texto del botón (opcional)')
                        ->helperText('En el hero aparece junto a los botones fijos.')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('subtitle')
                        ->label('Texto (opcional)')
                        ->helperText("Solo se muestra en 'Debajo de categorías'.")
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('link_url')->label('Enlace')->url()->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Imagen o video')
                ->schema([
                    Forms\Components\ToggleButtons::make('media_type')
                        ->label('Tipo')
                        ->options(['image' => 'Imagen', 'video' => 'Video'])
                        ->icons(['image' => 'heroicon-o-photo', 'video' => 'heroicon-o-film'])
                        ->default('image')
                        ->inline()
                        ->live()
                        ->required(),

                    Forms\Components\FileUpload::make('image_url')
                        ->label('Imagen')
                        ->image()
                        ->directory('banners')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->required(fn (Get $get) => ! $esVideo($get))
                        ->visible(fn (Get $get) => ! $esVideo($get)),

                    Forms\Components\Grid::make(2)
                        ->visible($esVideo)
                        ->schema([
                            Forms\Components\Placeholder::make('aviso_formatos')
                                ->hiddenLabel()
                                ->content('Los navegadores reproducen bien MP4 (H.264) y WebM. Si no se reproduce, conviértelo a MP4 con HandBrake.')
                                ->columnSpanFull(),
                            self::campoDeVideo('video_path', 'Video')
                                ->required($esVideo)
                                ->helperText('Obligatorio si el tipo es video. MP4 H.264, 720p o 1080p, sin límite de duración; para 2-3 minutos apunta a menos de 60 MB. Hasta 150 MB.'),
                            self::campoDeVideo('video_mobile_path', 'Video móvil (opcional)')
                                ->helperText('Opcional, para pantallas pequeñas: una versión más liviana o vertical. Si no lo subes, se usa el video principal. Hasta 150 MB.'),
                            Forms\Components\FileUpload::make('poster_path')
                                ->label('Poster (opcional)')
                                ->image()
                                ->directory('banners/posters')
                                ->visibility('public')
                                ->imagePreviewHeight('120')
                                ->helperText('Opcional, imagen de portada en 16:9. Recomendado: mejora la velocidad de carga y se ve mientras el video carga. Sin poster se muestra un fondo oscuro con el primer fotograma.'),
                            Forms\Components\Toggle::make('video_with_sound')
                                ->label('Con sonido disponible')
                                ->helperText('El video arranca siempre en silencio. Con esta opción, el visitante puede activar el sonido.')
                                ->default(false)
                                ->inline(false),
                        ]),
                ]),

            Forms\Components\Section::make('Ubicación')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('position')
                        ->label('Posición')
                        // 'sidebar' no se dibuja en ninguna página: solo se muestra para
                        // un banner viejo que la tenga, así no queda vacío al editarlo.
                        ->options(fn (?Banner $record) => Banner::POSICIONES
                            + ($record?->position === 'sidebar' ? ['sidebar' => 'Sidebar (no se muestra en la web)'] : []))
                        ->default('hero')
                        ->helperText('"Debajo de categorías" admite varios: se ordenan por "Orden".')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')->label('Activo')->default(true)->inline(false),
                    Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
                ]),
        ]);
    }

    /**
     * Subida de un video. Sin previsualización ni lectura del archivo al editar: con
     * un video de 150 MB, FilePond lo descargaría entero solo para mostrarlo.
     */
    private static function campoDeVideo(string $campo, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($campo)
            ->label($label)
            ->acceptedFileTypes(self::TIPOS_DE_VIDEO)
            ->mimeTypeMap(self::MIME_POR_EXTENSION)
            ->maxSize(self::MAX_VIDEO_KB)
            ->directory('banners/videos')
            ->visibility('public')
            ->previewable(false)
            ->fetchFileInformation(false);
    }

    /**
     * Al guardar quedan solo los campos del tipo elegido: los del otro se vacían (y el
     * modelo borra del disco el archivo que hubiera). Los campos ocultos no se envían,
     * por eso hay que ponerlos en null a mano. Lo usan las páginas de crear y editar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function limpiarSegunTipo(array $data): array
    {
        if (($data['media_type'] ?? 'image') === 'video') {
            return array_merge($data, ['image_url' => null]);
        }

        return array_merge($data, [
            'media_type' => 'image',
            'video_path' => null,
            'video_mobile_path' => null,
            'poster_path' => null,
            'video_with_sound' => false,
        ]);
    }

    public static function table(Table $table): Table
    {
        $posiciones = Banner::POSICIONES + ['sidebar' => 'Sidebar (sin uso)'];

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('vista')
                    ->label('Imagen')
                    ->disk('public')
                    ->getStateUsing(fn (Banner $r) => $r->image_url ?? $r->poster_path),
                Tables\Columns\TextColumn::make('title')->label('Título')->placeholder('Sin título')->searchable(),
                Tables\Columns\TextColumn::make('media_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'video' ? 'Video' : 'Imagen')
                    ->color(fn (?string $state) => $state === 'video' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('position')
                    ->label('Posición')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $posiciones[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'sidebar' ? 'warning' : 'primary'),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('position')->label('Posición')->options($posiciones),
                Tables\Filters\SelectFilter::make('media_type')->label('Tipo')->options(['image' => 'Imagen', 'video' => 'Video']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}

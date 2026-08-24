<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?string $navigationLabel = 'Artículos';

    protected static ?string $modelLabel = 'Artículo';

    protected static ?string $pluralModelLabel = 'Artículos';

    public static function form(Form $form): Form
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();

        return $form->schema([
            Forms\Components\Section::make('Contenido')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('Extracto')
                        ->maxLength(500)
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('content')
                        ->label('Contenido')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('Imagen de portada')
                        ->image()
                        ->disk('public')
                        ->directory('blog')
                        ->visibility('public')
                        ->imagePreviewHeight('160')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Publicación')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options($isAdmin
                            ? ['draft' => 'Borrador', 'published' => 'Publicado']
                            : ['draft' => 'Borrador'])
                        ->default('draft')
                        ->required()
                        // Solo el Administrador puede publicar; el Empleado queda
                        // bloqueado en Borrador aunque intente forzar el valor.
                        ->disabled(! $isAdmin)
                        ->dehydrated(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Fecha de publicación')
                        ->helperText('Si se deja vacío, se completa automáticamente al publicar.')
                        ->visible($isAdmin),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('author.full_name')
                    ->label('Autor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? 'Publicado' : 'Borrador')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Fecha')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(['draft' => 'Borrador', 'published' => 'Publicado']),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => (bool) auth()->user()?->isAdmin()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('author:id,first_name,last_name')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // ─── Permisos ───
    // Admin y Empleado ven y crean artículos; el Empleado solo edita los suyos
    // y ni edita el estado a "publicado" ni elimina (eso es exclusivo de Admin).
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (! $user?->isStaff()) {
            return false;
        }

        return $user->isAdmin() || $record->author_id === $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}

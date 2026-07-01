<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('parent_id')
                ->label('Categoría padre')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Ninguna (categoría raíz)'),
            Forms\Components\TextInput::make('icon')
                ->label('Ícono')
                ->placeholder('shield-half'),
            Forms\Components\FileUpload::make('image_url')
                ->label('Imagen')
                ->image()
                ->directory('categories')
                ->visibility('public'),
            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label('Ícono'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Padre')
                    ->badge()
                    ->placeholder('Raíz'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
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
        $u = auth()->user();
        return (bool) ($u?->isAdmin() || $u?->isEmpleado());
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

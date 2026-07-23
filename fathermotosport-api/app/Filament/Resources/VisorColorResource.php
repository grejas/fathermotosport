<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisorColorResource\Pages;
use App\Models\VisorColor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VisorColorResource extends Resource
{
    protected static ?string $model = VisorColor::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Visores';

    protected static ?string $modelLabel = 'Visor';

    protected static ?string $pluralModelLabel = 'Visores';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\ColorPicker::make('hex_color')->label('Color')->required(),
            Forms\Components\FileUpload::make('image_url')
                ->label('Imagen')
                ->image()
                ->disk('public')
                ->directory('visors')
                ->imagePreviewHeight('120'),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('hex_color')->label('Color'),
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\ImageColumn::make('image_url')->label('Imagen')->disk('public'),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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
            'index' => Pages\ListVisorColors::route('/'),
            'create' => Pages\CreateVisorColor::route('/create'),
            'edit' => Pages\EditVisorColor::route('/{record}/edit'),
        ];
    }
}

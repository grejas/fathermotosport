<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Equipo';

    protected static ?string $navigationLabel = 'Empleados';

    protected static ?string $modelLabel = 'Empleado';

    protected static ?string $pluralModelLabel = 'Empleados';

    /** Solo el Administrador gestiona el equipo. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** El listado solo muestra usuarios con rol Empleado. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'empleado'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('Nombre')
                ->required()
                ->maxLength(25)
                ->rules(['regex:/^[a-zA-ZÀ-ÿ\s]+$/'])
                ->validationMessages([
                    'regex' => 'El nombre solo puede contener letras (máx. 25 caracteres).',
                ]),
            Forms\Components\TextInput::make('last_name')
                ->label('Apellido')
                ->required()
                ->maxLength(25)
                ->rules(['regex:/^[a-zA-ZÀ-ÿ\s]+$/'])
                ->validationMessages([
                    'regex' => 'El apellido solo puede contener letras (máx. 25 caracteres).',
                ]),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(table: 'users', column: 'email', ignoreRecord: true)
                ->rules(['email:rfc,dns'])
                ->validationMessages([
                    'email' => 'Ingresa un email válido (verificá que el dominio exista).',
                ]),
            Forms\Components\TextInput::make('phone')
                ->label('Teléfono')
                ->maxLength(20)
                ->rules(['nullable', 'regex:/^(\+591[\s]?[67]\d{7}|\+55[\s]?\d{2}[\s]?\d{4,5}[-\s]?\d{4})$/'])
                ->validationMessages([
                    'regex' => 'Ingresá un número válido de Bolivia (+591) o Brasil (+55).',
                ])
                ->helperText('Formato: +591 XXXXXXXX (Bolivia) o +55 XX XXXXX-XXXX (Brasil).'),
            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->minLength(8)
                ->rules(['nullable', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'])
                ->validationMessages([
                    'regex' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.',
                ])
                // Requerida al crear; al editar, en blanco = mantener la actual.
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo. El empleado debería cambiarla al primer ingreso.'),
            Forms\Components\Toggle::make('is_active')
                ->label('Activo')
                ->default(true),
            // El rol Empleado se asigna automáticamente (no es seleccionable).
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (User $r) => $r->full_name)
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Activo')
                    ->boolean()
                    ->getStateUsing(fn (User $r) => $r->status === 'active'),
                Tables\Columns\TextColumn::make('created_at')->label('Creado')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->queries(
                        true: fn (Builder $q) => $q->where('status', 'active'),
                        false: fn (Builder $q) => $q->where('status', '!=', 'active'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (User $r) => $r->status === 'active' ? 'Desactivar' : 'Activar')
                    ->icon('heroicon-o-power')
                    ->requiresConfirmation()
                    ->action(fn (User $r) => $r->update([
                        'status' => $r->status === 'active' ? 'inactive' : 'active',
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}

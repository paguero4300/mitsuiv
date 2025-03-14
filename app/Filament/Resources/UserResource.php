<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static bool $shouldCreateAnother = false;

    public static function getNavigationLabel(): string
    {
        return trans('filament-users::user.resource.label');
    }

    public static function getPluralLabel(): string
    {
        return trans('filament-users::user.resource.label');
    }

    public static function getLabel(): string
    {
        return trans('filament-users::user.resource.single');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-users.group');
    }

    public function getTitle(): string
    {
        return trans('filament-users::user.resource.title.resource');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Información del Usuario')
                ->description('Ingresa la información básica del usuario.')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->placeholder('Nombre completo')
                        ->label(trans('filament-users::user.resource.name'))
                        ->columnSpan(1),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->placeholder('correo@ejemplo.com')
                        ->label(trans('filament-users::user.resource.email'))
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated(true)
                        ->columnSpan(1),
                    TextInput::make('custom_fields.phone')
                        ->label('Teléfono')
                        ->tel()
                        ->placeholder('51999999999')
                        ->helperText('Ingrese el número con formato 51 + 9 dígitos. Ejemplo: 51999999999')
                        ->regex('/^51[0-9]{9}$/')
                        ->validationAttribute('teléfono')
                        ->columnSpan(1),
                ]),

            Section::make('Seguridad')
                ->description('Gestión de contraseña y roles del usuario.')
                ->icon('heroicon-o-lock-closed')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('change_password')
                        ->label('¿Cambiar contraseña?')
                        ->default(false)
                        ->visible(fn ($record) => $record !== null && !$record->hasRole('super_admin'))
                        ->columnSpan(2),
                    TextInput::make('password')
                        ->label(trans('filament-users::user.resource.password'))
                        ->type(fn ($get) => $get('show_password') ? 'text' : 'password')
                        ->required(fn ($record) => ! $record)
                        ->visible(fn ($get, $record) => (!$record || $get('change_password')) && (!$record || !$record->hasRole('super_admin')))
                        ->minLength(8)
                        ->placeholder('Mínimo 8 caracteres')
                        ->live()
                        ->suffixActions([
                            Forms\Components\Actions\Action::make('togglePassword')
                                ->icon(fn ($get) => $get('show_password') ? 'heroicon-m-eye-slash' : 'heroicon-m-eye')
                                ->label(fn ($get) => $get('show_password') ? 'Ocultar' : 'Ver')
                                ->color('primary')
                                ->button()
                                ->action(function (Forms\Set $set, $get) {
                                    $set('show_password', !$get('show_password'));
                                }),
                            Forms\Components\Actions\Action::make('generatePassword')
                                ->icon('heroicon-m-key')
                                ->label('Generar')
                                ->color('primary')
                                ->button()
                                ->action(function ($component) {
                                    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
                                    $password = substr(str_shuffle($characters), 0, 12);
                                    $component->state($password);
                                })
                        ])
                        ->helperText('La contraseña debe tener al menos 8 caracteres')
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(static function ($state) {
                            return filled($state) ? Hash::make($state) : null;
                        })
                        ->columnSpan(2),
                    Forms\Components\Hidden::make('show_password')
                        ->default(false),
                    Forms\Components\Select::make('roles')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->preload()
                        ->searchable()
                        ->visible(fn () => config('filament-users.shield') && class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class))
                        ->disabled(fn ($record) => $record && $record->hasRole('super_admin'))
                        ->label('Roles')
                        ->helperText('Seleccione uno o más roles para el usuario')
                        ->columnSpanFull()
                        ->native(false)
                        ->loadingMessage('Cargando roles...')
                        ->noSearchResultsMessage('No se encontraron roles')
                        ->searchingMessage('Buscando...')
                        ->selectablePlaceholder('Seleccionar')
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->label(trans('filament-users::user.resource.name'))
                    ->weight('medium')
                    ->description(fn (User $record): string => $record->email)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('custom_fields.phone')
                    ->label('Teléfono')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}" : '-')
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->copyMessageDuration(1500)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('Perfil')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->roles->pluck('name')->map(fn ($name) => ucfirst($name)))
                    ->color(fn ($state, $record): string => match ($record->roles->first()?->name) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'tasador' => 'success',
                        'revendedor' => 'info',
                        default => 'gray',
                    })
                    ->visible(fn () => config('filament-users.shield') && class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class))
                    ->toggleable()
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Perfil')
                    ->options([
                        'tasador' => 'Tasador',
                        'revendedor' => 'Revendedor',
                        'administrador' => 'Administrador',
                        'admin de visualizacion' => 'Admin de visualizacion'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'] ?? [])) {
                            return $query;
                        }

                        return $query->whereHas('roles', function ($query) use ($data) {
                            $query->whereIn('name', $data['values']);
                        });
                    })
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ], layout: \Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn ($action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-m-funnel')
                    ->color('gray')
                    ->size('sm')
            )
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver')
                        ->color('info'),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->visible(fn ($record) => !$record->hasRole('super_admin'))
                        ->color('warning'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->visible(fn ($record) => !$record->hasRole('super_admin'))
                        ->color('danger'),
                ])
                ->icon('heroicon-m-cog-6-tooth')
                ->button()
                ->color('gray')
                ->size('sm'),
            ])
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50])
            ->poll('60s')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Verificar si el usuario actual es super_admin
        $isSuperAdmin = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'mhr.role_id', '=', 'r.id')
            ->where('mhr.model_id', Auth::id())
            ->where('mhr.model_type', User::class)
            ->where('r.name', 'super_admin')
            ->exists();

        // Si NO es super_admin, excluir a los usuarios super_admin del listado
        if (!$isSuperAdmin) {
            // Excluir usuarios que tienen rol super_admin
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->join('roles as r', 'mhr.role_id', '=', 'r.id')
                    ->whereColumn('mhr.model_id', 'users.id')
                    ->where('mhr.model_type', User::class)
                    ->where('r.name', 'super_admin');
            });
        }

        return $query;
    }
}

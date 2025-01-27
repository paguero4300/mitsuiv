<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogValueResource\Pages;
use App\Models\CatalogValue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class CatalogValueResource extends Resource
{
    // Modelo asociado
    protected static ?string $model = CatalogValue::class;

    // Icono del menú en Filament
    protected static ?string $navigationIcon = 'heroicon-o-book-open';


    // Orden en el menú
    protected static ?int $navigationSort = 3;

    // Nombre singular en español
    protected static ?string $label = 'Valor de Catálogo';

    // Nombre plural en español
    protected static ?string $pluralLabel = 'Valores';

    // Grupo de navegación
    protected static ?string $navigationGroup = 'Catálogos';

    /**
     * Formulario para crear y editar registros.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Grid::make()
                        ->schema([
                            Forms\Components\Select::make('catalog_type_id')
                                ->label('Tipo de Catálogo')
                                ->options(
                                    \App\Models\CatalogType::pluck('name', 'id')
                                        ->map(fn($name) => strtoupper($name))
                                        ->toArray()
                                )
                                ->required()
                                ->placeholder('Selecciona el tipo de catálogo')
                                ->helperText('Selecciona el tipo de catálogo correspondiente')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\Select::make('parent_id')
                                        ->label('Marca Asociada')
                                        ->relationship('parent', 'value', fn($query) => $query->where('catalog_type_id', 1))
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Selecciona la marca (si aplica)')
                                        ->helperText('Solo aplica para tipo "Vehículo"')
                                ])
                                ->hidden(fn(Get $get) => $get('catalog_type_id') !== 2)
                                ->columns(1)
                                ->columnSpan('full'),

                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\TextInput::make('value')
                                        ->label('Valor')
                                        ->placeholder('Ejemplo: Corolla, Camry, Azul...')
                                        ->required()
                                        ->maxLength(255)
                                        ->prefixIcon('heroicon-o-pencil'),

                                    Forms\Components\TextInput::make('description')
                                        ->label('Descripción')
                                        ->placeholder('Descripción del valor...')
                                        ->maxLength(255)
                                        ->prefixIcon('heroicon-o-document-text'),

                                    Forms\Components\Toggle::make('active')
                                        ->label('Activo')
                                        ->required()
                                        ->inline()
                                        ->onColor('success')
                                        ->offColor('danger'),
                                ])
                                ->columns([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                ->columnSpan('full'),
                        ])
                        ->columns(12)
                ])
                ->columns([
                    'default' => 1,
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 1,
                    'xl' => 1,
                ])
                ->compact()
        ]);
    }


    /**
     * Configuración de la tabla para mostrar los registros.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('type.name')
                            ->label('Tipo de Catálogo')
                            ->sortable()
                            ->searchable()
                            ->formatStateUsing(fn(string $state): string => strtoupper($state))
                            ->icon('heroicon-m-tag')
                            ->weight('bold')
                            ->color('primary'),

                        Tables\Columns\TextColumn::make('value')
                            ->label('Valor')
                            ->sortable()
                            ->searchable(),
                    ]),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('parent.value')
                            ->label('Marca Asociada')
                            ->sortable()
                            ->searchable()
                            ->toggleable()
                            ->visibleFrom('md'),

                        Tables\Columns\TextColumn::make('description')
                            ->label('Descripción')
                            ->sortable()
                            ->searchable()
                            ->limit(30),
                    ]),

                    Tables\Columns\IconColumn::make('active')
                        ->label('Activo')
                        ->boolean()
                        ->alignCenter(),
                ])->from('md'),
            ])
            ->defaultSort('type.name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('catalog_type_id')
                    ->label('Catálogo')
                    ->relationship('type', 'name', fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->optionsLimit(15)
                    ->getOptionLabelFromRecordUsing(fn ($record) => strtoupper($record->name))
                    ->columnSpan([
                        'default' => 2,
                        'sm' => 1,
                        'md' => 1,
                    ]),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Marca')
                    ->relationship('parent', 'value', fn ($query) => $query->where('catalog_type_id', 1)->orderBy('value'))
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => strtoupper($record->value))
                    ->columnSpan([
                        'default' => 2,
                        'sm' => 1,
                        'md' => 1,
                    ]),
            ])
            ->filtersFormColumns([
                'default' => 2,
                'sm' => 2,
                'md' => 2,
            ])
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filtrar')
                    ->icon('heroicon-m-funnel')
                    ->size('sm')
            )
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-m-trash')
                    ->before(function (Tables\Actions\DeleteAction $action, CatalogValue $record) {
                        $vehicleCount = \App\Models\Vehicle::where(function ($query) use ($record) {
                            $query->where('brand_id', $record->id)
                                ->orWhere('model_id', $record->id)
                                ->orWhere('transmission_id', $record->id)
                                ->orWhere('body_type_id', $record->id)
                                ->orWhere('cylinders_id', $record->id)
                                ->orWhere('fuel_type_id', $record->id)
                                ->orWhere('doors_id', $record->id)
                                ->orWhere('traction_id', $record->id)
                                ->orWhere('color_id', $record->id)
                                ->orWhere('location_id', $record->id);
                        })->count();

                        if ($vehicleCount > 0) {
                            $action->cancel();
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('¡Error al Eliminar!')
                                ->body('Este valor no se puede eliminar porque está siendo utilizado en ' . $vehicleCount . ' vehículo(s).')
                                ->persistent()
                                ->send();
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            $usedValues = \App\Models\Vehicle::where(function ($query) use ($records) {
                                $recordIds = $records->pluck('id')->toArray();
                                $query->whereIn('brand_id', $recordIds)
                                    ->orWhereIn('model_id', $recordIds)
                                    ->orWhereIn('transmission_id', $recordIds)
                                    ->orWhereIn('body_type_id', $recordIds)
                                    ->orWhereIn('cylinders_id', $recordIds)
                                    ->orWhereIn('fuel_type_id', $recordIds)
                                    ->orWhereIn('doors_id', $recordIds)
                                    ->orWhereIn('traction_id', $recordIds)
                                    ->orWhereIn('color_id', $recordIds)
                                    ->orWhereIn('location_id', $recordIds);
                            })->count();

                            if ($usedValues > 0) {
                                $action->cancel();
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('¡Error al Eliminar!')
                                    ->body('No se pueden eliminar los valores seleccionados porque están siendo utilizados en vehículos.')
                                    ->persistent()
                                    ->send();
                                return false;
                            }
                        }),
                ])->label('Acciones masivas'),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Relaciones definidas para este recurso.
     */
    public static function getRelations(): array
    {
        return [
            // Aquí puedes agregar gestores de relaciones adicionales si es necesario
        ];
    }

    /**
     * Definición de las páginas del recurso.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogValues::route('/'),
            'create' => Pages\CreateCatalogValue::route('/crear'),
            'edit' => Pages\EditCatalogValue::route('/{record}/editar'),
        ];
    }

    // Agregar este nuevo método
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    // Configuración adicional para el formulario de creación
    public static function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('saveAndCreateAnother')
                ->label('Guardar y Crear Otro')
                ->action(function (Forms\Form $form) {
                    $form->getResource()::create($form->getState());
                    
                    // Redireccionar al formulario de creación
                    redirect(static::getUrl('create'));
                })
                ->color('success')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public static function getFormModel(): string
    {
        return CatalogValue::class;
    }
}

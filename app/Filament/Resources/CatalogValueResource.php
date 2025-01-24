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
    protected static ?string $navigationGroup = 'Catálogos del Sistema';

    /**
     * Formulario para crear y editar registros.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            // Sección principal del formulario
            Forms\Components\Section::make('Información del Valor de Catálogo')
                ->schema([
                    // Tipo de Catálogo
                    Forms\Components\Select::make('catalog_type_id')
                        ->label('Tipo de Catálogo')
                        ->options(
                            \App\Models\CatalogType::pluck('name', 'id')
                                ->map(fn($name) => strtoupper($name)) // Opciones en mayúsculas
                                ->toArray()
                        )
                        ->required()
                        ->placeholder('Selecciona el tipo de catálogo')
                        ->hint('Elige el tipo de catálogo al que pertenece este valor.')
                        ->columnSpanFull(), // Ocupa toda la fila para mejor visibilidad

                    // Marca Asociada (visible solo si el tipo de catálogo es "Vehículo")
                    Forms\Components\Select::make('parent_id')
                        ->label('Marca Asociada')
                        ->relationship('parent', 'value', fn($query) => $query->where('catalog_type_id', 1)) // Filtrar solo marcas
                        ->searchable()
                        ->preload()
                        ->placeholder('Selecciona la marca (si aplica)')
                        ->hint('Este campo se muestra solo si el tipo de catálogo es "Vehículo".')
                        ->hidden(fn(\Filament\Forms\Get $get) => $get('catalog_type_id') !== 2) // Ocultar si no es Vehículo
                        ->columnSpan(1),

                    // Valor
                    Forms\Components\TextInput::make('value')
                        ->label('Valor')
                        ->placeholder('Ejemplo: Corolla, Camry, Azul...')
                        ->required()
                        ->maxLength(255)
                        ->prefixIcon('heroicon-o-pencil') // Icono al inicio del input
                        ->hint('Ingresa el valor relacionado con el catálogo.')
                        ->columnSpan(1),

                    // Descripción
                    Forms\Components\TextInput::make('description')
                        ->label('Descripción')
                        ->placeholder('Descripción del valor...')
                        ->maxLength(255)
                        ->prefixIcon('heroicon-o-document-text') // Icono al inicio del input
                        ->hint('Agrega una breve descripción para este valor.')
                        ->columnSpanFull(), // Ocupa toda la fila

                    // Activo
                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->required()
                        ->inline(false) // Toggle debajo del label
                        ->hint('Indica si este valor estará activo en el sistema.')
                        ->columnSpanFull(), // Ocupa toda la fila
                ])
                ->columns([
                    'sm' => 1, // 1 columna en pantallas pequeñas
                    'lg' => 2, // 2 columnas en pantallas grandes
                ])
                ->description('Completa la información requerida para registrar o actualizar un valor en el catálogo.'),
        ]);
    }


    /**
     * Configuración de la tabla para mostrar los registros.
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('type.name')
                ->label('Tipo de Catálogo')
                ->sortable()
                ->searchable()
                ->formatStateUsing(fn(string $state): string => strtoupper($state)),

            Tables\Columns\TextColumn::make('value')
                ->label('Valor')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('parent.value')
                ->label('Marca Asociada') // Muestra la Marca asociada al Modelo
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('description')
                ->label('Descripción')
                ->sortable()
                ->searchable(),

            Tables\Columns\IconColumn::make('active')
                ->label('Activo')
                ->boolean(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Creado el')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Actualizado el')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->filters([
                // Filtro opcional para buscar por Marca
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Filtrar por Marca')
                    ->relationship('parent', 'value'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
}

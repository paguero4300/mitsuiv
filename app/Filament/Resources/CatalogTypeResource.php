<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogTypeResource\Pages;
use App\Models\CatalogType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CatalogTypeResource extends Resource
{
    // Modelo asociado a este recurso
    protected static ?string $model = CatalogType::class;

    // Icono de navegación en el menú de Filament
    protected static ?string $navigationIcon = 'heroicon-o-folder';

    // Nombre singular del recurso
    protected static ?string $recordTitleAttribute = 'name';

    // Título singular en español
    protected static ?string $label = 'Tipo de Catálogo';

    // Título plural en español
    protected static ?string $pluralLabel = 'Tipos';


    protected static ?string $navigationGroup = 'Catálogos';

    // Deshabilitamos la creación de nuevos registros
    public static function canCreate(): bool
    {
        return false;
    }

    // Deshabilitamos la edición de registros
    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    // Deshabilitamos la eliminación de registros
    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    /**
     * Formulario para crear y editar registros de tipos de catálogos.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Información Principal')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre del Tipo de Catálogo') // Etiqueta en español
                        ->placeholder('Ejemplo: Marca, Modelo, Color...')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('description')
                        ->label('Descripción')
                        ->placeholder('Ejemplo: Catálogo para almacenar marcas...')
                        ->maxLength(255),

                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->required(),
                ])
                ->columnSpan('full'), // Hace que todos los campos estén en una columna completa
        ]);
    }

    /**
     * Configuración de la tabla para mostrar los registros.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->formatStateUsing(fn(string $state): string => strtoupper($state))
                    ->wrap() // Permite que el texto se ajuste en móviles
                    ->grow(false), // Evita que la columna ocupe demasiado espacio

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap() // Permite que el texto se ajuste en móviles
                    ->grow(true), // Permite que esta columna crezca

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([]) // Eliminamos todas las acciones
            ->bulkActions([]) // Eliminamos las acciones en masa
            ->defaultSort('name', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('60s'); // Actualiza la tabla cada 60 segundos
    }

    /**
     * Relaciones definidas para este recurso.
     */
    public static function getRelations(): array
    {
        return [
            // Aquí puedes agregar gestores de relaciones, como CatalogValuesRelationManager
        ];
    }

    /**
     * Definición de las páginas del recurso.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogTypes::route('/'),
        ];
    }
}

<?php
// app/Filament/Resources/AuctionSettingResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionSettingResource\Pages;
use App\Models\AuctionSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuctionSettingResource extends Resource
{
    protected static ?string $model = AuctionSetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Configuración';
    protected static ?string $pluralModelLabel = 'Configuraciones de Subastas';
    protected static ?string $modelLabel = 'Incrementos';
    protected static ?string $navigationGroup = 'Configuración Subastas';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Identificador')
                            ->placeholder('Se generará automáticamente')
                            ->maxLength(255)
                            ->disabled()
                            ->hidden(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ingrese una descripción')
                            ->disabled()
                            ->hidden(),

                        Forms\Components\Repeater::make('temp_value')
                            ->label('Rangos de Incremento')
                            ->schema([
                                Forms\Components\Select::make('range_name')
                                    ->label('Nombre del Rango')
                                    ->required()
                                    ->disabled()
                                    ->options([
                                        'Rango Bajo' => 'Rango Bajo',
                                        'Rango Medio' => 'Rango Medio',
                                        'Rango Alto' => 'Rango Alto',
                                    ]),

                                Forms\Components\TextInput::make('min_value')
                                    ->label('Valor Mínimo')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0),

                                Forms\Components\TextInput::make('max_value')
                                    ->label('Valor Máximo')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(fn(Forms\Get $get) =>
                                    (int)$get('min_value') + 1),

                                Forms\Components\TextInput::make('increment')
                                    ->label('Incremento')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(1),
                            ])
                            ->columns(2)
                            ->maxItems(3)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->itemLabel(fn(array $state): ?string =>
                            $state['range_name'] ?? 'Nuevo Rango')
                            ->live()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && isset($record->value)) {
                                    if (is_string($record->value)) {
                                        $decodedValue = json_decode($record->value, true);
                                        if (is_array($decodedValue)) {
                                            $component->state($decodedValue);
                                        }
                                    }
                                }
                            })
                            ->dehydrated(false)
                            ->addable(false)
                            ->deletable(false),
                        // Campo oculto que realmente se guardará
                        Forms\Components\Hidden::make('value')
                            ->dehydrateStateUsing(function ($state, Forms\Get $get) {
                                $tempValue = $get('temp_value');
                                return is_array($tempValue) ? json_encode($tempValue) : '[]';
                            })
                            ->afterStateHydrated(function ($component, $state) {
                                if (is_string($state)) {
                                    $decodedValue = json_decode($state, true);
                                    if (is_array($decodedValue)) {
                                        $component->state(json_encode($decodedValue));
                                    }
                                }
                            }),
                    ])
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Identificador')
                    ->searchable()
                    ->sortable()
                    ->hidden(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Configuración de Rangos')
                    ->formatStateUsing(function ($state) {
                        $data = is_string($state) ? json_decode($state, true) : $state;

                        if (!is_array($data)) {
                            return 'Sin configuración';
                        }

                        return collect($data)->map(function ($range) {
                            return sprintf(
                                '%s: Mínimo $%s - Máximo $%s (Incremento: $%s)',
                                $range['range_name'] ?? 'Sin nombre',
                                number_format($range['min_value'] ?? 0, 0, ',', '.'),
                                number_format($range['max_value'] ?? 0, 0, ',', '.'),
                                number_format($range['increment'] ?? 0, 0, ',', '.')
                            );
                        })->join('<br>');
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ]);  // Eliminamos bulkActions
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuctionSettings::route('/'),
            'edit' => Pages\EditAuctionSetting::route('/{record}/edit'),
        ];
    }
}

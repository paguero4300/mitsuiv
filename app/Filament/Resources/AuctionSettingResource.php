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
                    ->description(fn ($record) => $record ? "Editando configuración de {$record->description}" : 'Creando nueva configuración')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Forms\Components\Repeater::make('temp_value')
                            ->label('Rangos de Incremento')
                            ->schema([
                                Forms\Components\Hidden::make('range_name'),

                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('min_value')
                                            ->label('Valor Mínimo')
                                            ->required()
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0)
                                            ->placeholder('0')
                                            ->hint('Valor inicial del rango')
                                            ->extraInputAttributes(['class' => 'text-lg']),

                                        Forms\Components\TextInput::make('max_value')
                                            ->label('Valor Máximo')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(fn (Forms\Get $get) => (int)$get('min_value') + 1)
                                            ->required(fn (Forms\Get $get) => $get('range_name') !== 'Rango Alto')
                                            ->nullable()
                                            ->placeholder('Valor máximo o vacío para sin límite')
                                            ->hint('Dejar vacío para rango alto')
                                            ->extraInputAttributes(['class' => 'text-lg'])
                                            ->rules([
                                                function (Forms\Get $get) {
                                                    return function ($attribute, $value, $fail) use ($get) {
                                                        if ($get('range_name') !== 'Rango Alto' && empty($value)) {
                                                            $fail('El valor máximo es requerido para este rango.');
                                                        }
                                                    };
                                                },
                                            ]),

                                        Forms\Components\TextInput::make('increment')
                                            ->label('Incremento')
                                            ->required()
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(1)
                                            ->placeholder('Valor de incremento')
                                            ->hint('Cantidad a incrementar en cada puja')
                                            ->extraInputAttributes(['class' => 'text-lg']),
                                    ])
                                    ->columns(['default' => 1, 'sm' => 3])
                                    ->columnSpan('full'),
                            ])
                            ->columns(1)
                            ->maxItems(1)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $value = is_string($record->value) ? json_decode($record->value, true) : $record->value;
                                    if (is_array($value) && !empty($value)) {
                                        $rango = $value[0];
                                        $component->state([$rango]);
                                    }
                                } else {
                                    $component->state([
                                        ['range_name' => 'Rango Bajo', 'min_value' => null, 'max_value' => null, 'increment' => null],
                                    ]);
                                }
                            })
                            ->dehydrated(false),

                        Forms\Components\Hidden::make('value')
                            ->dehydrateStateUsing(function ($state, Forms\Get $get) {
                                $tempValue = $get('temp_value');
                                return $tempValue;
                            }),
                    ])
                    ->collapsible()
                    ->columns(['default' => 1, 'sm' => 1])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    ->size('md'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Configuración de Rangos')
                    ->formatStateUsing(function ($state) {
                        try {
                            if (empty($state)) {
                                return 'Sin configuración';
                            }

                            $data = is_string($state) ? json_decode($state, true) : $state;

                            if (!is_array($data)) {
                                return 'Formato inválido';
                            }

                            if (isset($data[0]) && is_array($data[0])) {
                                $rangos = $data;
                            } else {
                                $rangos = [$data];
                            }

                            return collect($rangos)->map(function ($range) {
                                if (!isset($range['range_name'])) {
                                    return 'Rango sin nombre';
                                }

                                $minValue = isset($range['min_value']) 
                                    ? number_format((float)$range['min_value'], 0, ',', '.') 
                                    : '0';

                                $maxValue = $range['max_value'] === null 
                                    ? 'Sin límite'
                                    : number_format((float)$range['max_value'], 0, ',', '.');

                                $increment = isset($range['increment']) 
                                    ? number_format((float)$range['increment'], 0, ',', '.') 
                                    : '0';

                                return sprintf(
                                    '<div class="space-y-1">
                                        <div class="font-medium text-gray-900">%s</div>
                                        <div class="text-sm">
                                            <span class="text-gray-500">Mínimo:</span> <span class="font-medium">$%s</span>
                                            <span class="mx-1">•</span>
                                            <span class="text-gray-500">Máximo:</span> <span class="font-medium">%s%s</span>
                                            <span class="mx-1">•</span>
                                            <span class="text-gray-500">Incremento:</span> <span class="font-medium">$%s</span>
                                        </div>
                                    </div>',
                                    $range['range_name'],
                                    $minValue,
                                    $maxValue !== 'Sin límite' ? '$' : '',
                                    $maxValue,
                                    $increment
                                );
                            })->join('<div class="my-2 border-t border-gray-200"></div>');

                        } catch (\Exception $e) {
                            return 'Error: ' . $e->getMessage();
                        }
                    })
                    ->html()
                    ->wrap(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square'),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuctionSettings::route('/'),
            'edit' => Pages\EditAuctionSetting::route('/{record}/edit'),
        ];
    }
}

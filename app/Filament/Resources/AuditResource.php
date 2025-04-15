<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Filament\Resources\AuditResource\RelationManagers;
use App\Models\Audit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $modelLabel = 'Registro de Auditoría';

    protected static ?string $pluralModelLabel = 'Registros de Auditoría';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationGroup = 'Administración';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles de la Auditoría')
                    ->schema([
                        Forms\Components\TextInput::make('user_id')
                            ->label('Usuario')
                            ->formatStateUsing(function ($state) {
                                if (!$state) return 'Sistema';
                                $user = \App\Models\User::find($state);
                                return $user ? $user->name : "Usuario #{$state}";
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('event')
                            ->label('Acción')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'created' => 'Creado',
                                'updated' => 'Actualizado',
                                'deleted' => 'Eliminado',
                                'restored' => 'Restaurado',
                                default => $state,
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('modelo_descriptivo')
                            ->label('Tipo')
                            ->formatStateUsing(function ($state, $record) {
                                $modelMap = [
                                    'App\\Models\\Auction' => 'Subasta',
                                    'App\\Models\\Bid' => 'Puja',
                                    'App\\Models\\User' => 'Usuario',
                                    'App\\Models\\Vehicle' => 'Vehículo',
                                ];
                                
                                return $modelMap[$record->auditable_type] ?? class_basename($record->auditable_type);
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('auditable_id')
                            ->label('ID del Modelo')
                            ->formatStateUsing(function ($state, $record) {
                                // Si es un modelo que tiene un campo "name" o similar
                                if ($record->auditable_type === 'App\\Models\\User') {
                                    $model = \App\Models\User::find($state);
                                    return $model ? "{$state} ({$model->name})" : $state;
                                }
                                
                                // Si es un modelo Vehicle, mostramos la placa
                                if ($record->auditable_type === 'App\\Models\\Vehicle') {
                                    $model = \App\Models\Vehicle::find($state);
                                    return $model ? "{$state} ({$model->plate})" : $state;
                                }
                                
                                // Si es un modelo Auction, mostramos el ID del vehículo
                                if ($record->auditable_type === 'App\\Models\\Auction') {
                                    $model = \App\Models\Auction::find($state);
                                    return $model ? "{$state} (Vehículo: {$model->vehicle_id})" : $state;
                                }
                                
                                // Si es un modelo Bid, mostramos información de la puja
                                if ($record->auditable_type === 'App\\Models\\Bid') {
                                    $model = \App\Models\Bid::find($state);
                                    return $model ? "{$state} (Monto: $" . number_format($model->amount, 2) . ")" : $state;
                                }
                                
                                return $state;
                            })
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Fecha y Hora')
                            ->disabled(),
                    ]),
                Forms\Components\Section::make('Valores Modificados')
                    ->schema([
                        Forms\Components\KeyValue::make('old_values')
                            ->label('Valores Anteriores')
                            ->disabled(),
                        Forms\Components\KeyValue::make('new_values')
                            ->label('Valores Nuevos')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Usuario ID')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'Sistema';
                        $user = \App\Models\User::find($state);
                        return $user ? $user->name : "Usuario #{$state}";
                    })
                    ->description(fn ($record) => $record->ip_address ?? null),
                Tables\Columns\TextColumn::make('event')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'restored' => 'Restaurado',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->description(function ($record) {
                        $modelMap = [
                            'App\\Models\\Auction' => 'Subasta',
                            'App\\Models\\Bid' => 'Puja',
                            'App\\Models\\User' => 'Usuario',
                            'App\\Models\\Vehicle' => 'Vehículo',
                        ];
                        
                        return $modelMap[$record->auditable_type] ?? class_basename($record->auditable_type);
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID')
                    ->formatStateUsing(function ($state, $record) {
                        // Si es un modelo que tiene un campo "name" o similar
                        if ($record->auditable_type === 'App\\Models\\User') {
                            $model = \App\Models\User::find($state);
                            return $model ? "{$state} ({$model->name})" : $state;
                        }
                        
                        // Si es un modelo Vehicle, mostramos la placa
                        if ($record->auditable_type === 'App\\Models\\Vehicle') {
                            $model = \App\Models\Vehicle::find($state);
                            return $model ? "{$state} ({$model->plate})" : $state;
                        }
                        
                        // Si es un modelo Auction, mostramos el ID del vehículo
                        if ($record->auditable_type === 'App\\Models\\Auction') {
                            $model = \App\Models\Auction::find($state);
                            return $model ? "{$state} (Vehículo: {$model->vehicle_id})" : $state;
                        }
                        
                        // Si es un modelo Bid, mostramos información de la puja
                        if ($record->auditable_type === 'App\\Models\\Bid') {
                            $model = \App\Models\Bid::find($state);
                            return $model ? "{$state} (Monto: $" . number_format($model->amount, 2) . ")" : $state;
                        }
                        
                        return $state;
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Acción')
                    ->options([
                        'created' => '⚡ Creado',
                        'updated' => '✏️ Actualizado',
                        'deleted' => '🗑️ Eliminado',
                    ])
                    ->multiple()
                    ->indicateUsing(function (array $state): array {
                        $indicators = [];
                        
                        if ($state !== []) {
                            $indicators['Estado'] = '🔍 Filtrado por acciones: ' . count($state);
                        }
                        
                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Tipo de Modelo')
                    ->options(function () {
                        $types = Audit::distinct()->pluck('auditable_type')->toArray();
                        
                        $modelMap = [
                            'App\\Models\\Auction' => '💰 Subasta',
                            'App\\Models\\Bid' => '💵 Puja',
                            'App\\Models\\User' => '👤 Usuario',
                            'App\\Models\\Vehicle' => '🚗 Vehículo',
                        ];
                        
                        $result = [];
                        foreach ($types as $type) {
                            $result[$type] = $modelMap[$type] ?? '📄 ' . class_basename($type);
                        }
                        
                        return $result;
                    })
                    ->multiple()
                    ->indicateUsing(function (array $state): array {
                        $indicators = [];
                        
                        if ($state !== []) {
                            $indicators['Modelo'] = '🔍 Filtrado por tipos: ' . count($state);
                        }
                        
                        return $indicators;
                    }),
            ], layout: \Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn ($action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-m-funnel')
                    ->color('primary')
                    ->size('sm')
            )
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Las acciones masivas no son necesarias para los registros de auditoría
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
            'view' => Pages\ViewAudit::route('/{record}'),
            // No permitiremos crear o editar registros de auditoría
        ];
    }

    public static function canCreate(): bool
    {
        // Usar el sistema de permisos de Shield en lugar de hardcodear false
        return false; // Auditoría nunca debe crearse manualmente
    }

    public static function canEdit(Model $record): bool
    {
        // Usar el sistema de permisos de Shield en lugar de hardcodear false
        return false; // Auditoría nunca debe editarse
    }

    public static function canDelete(Model $record): bool
    {
        // Usar el sistema de permisos de Shield en lugar de hardcodear false
        return false; // Auditoría nunca debe eliminarse
    }
}

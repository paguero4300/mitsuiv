<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationSettingResource\Pages;
use App\Filament\Resources\NotificationSettingResource\RelationManagers;
use App\Models\NotificationSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class NotificationSettingResource extends Resource
{
    protected static ?string $model = NotificationSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Configuración de Notificaciones';
    protected static ?string $modelLabel = 'Configuración de Notificación';
    protected static ?string $pluralModelLabel = 'Configuraciones de Notificaciones';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('role_type')
                    ->label('Tipo de Rol')
                    ->disabled(),
                Forms\Components\TextInput::make('event_type')
                    ->label('Tipo de Evento')
                    ->disabled(),
                Forms\Components\Select::make('channel_id')
                    ->label('Canal')
                    ->relationship('channel', 'channel_type')
                    ->disabled(),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Habilitado')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('role_type')
                    ->label('Tipo de Rol')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->icon(fn (string $state): string => match ($state) {
                        'revendedor' => 'heroicon-o-shopping-cart',
                        'tasador' => 'heroicon-o-scale',
                        default => 'heroicon-o-user-group',
                    })
                    ->iconColor(fn (string $state): string => match ($state) {
                        'revendedor' => 'primary',
                        'tasador' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipo de Evento')
                    ->badge()
                    ->formatStateUsing(function (string $state, Model $record): string {
                        $descriptions = NotificationSetting::EVENT_TYPES[$record->role_type] ?? [];
                        return $descriptions[$state] ?? $state;
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'nueva_subasta', 'subasta_creada' => 'success',
                        'primera_puja', 'nueva_puja' => 'info',
                        'puja_superada' => 'warning',
                        'subasta_por_terminar', 'recordatorio_adjudicacion' => 'danger',
                        'subasta_ganada', 'confirmacion_adjudicacion' => 'success',
                        'subasta_adjudicada' => 'success',
                        'subasta_fallida' => 'danger',
                        'subasta_cerrada' => 'gray',
                        default => 'primary',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('channel.channel_type')
                    ->label('Canal')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->icon(fn (string $state): string => match ($state) {
                        'whatsapp' => 'heroicon-o-chat-bubble-left-right',
                        'email' => 'heroicon-o-envelope',
                        default => 'heroicon-o-bell-alert',
                    })
                    ->iconColor(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'email' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Habilitado')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('role_type')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Solo permitir cambiar is_enabled
                        return [
                            'is_enabled' => $data['is_enabled'],
                        ];
                    }),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListNotificationSettings::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

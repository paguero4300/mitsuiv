<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationChannelResource\Pages;
use App\Filament\Resources\NotificationChannelResource\RelationManagers;
use App\Models\NotificationChannel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Canales de Notificación';
    protected static ?string $modelLabel = 'Canal de Notificación';
    protected static ?string $pluralModelLabel = 'Canales de Notificación';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('channel_type')
                    ->label('Tipo de Canal')
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
                Tables\Columns\TextColumn::make('channel_type')
                    ->label('Tipo de Canal')
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
            'index' => Pages\ListNotificationChannels::route('/'),
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

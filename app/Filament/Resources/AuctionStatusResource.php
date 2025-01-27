<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionStatusResource\Pages;
use App\Models\AuctionStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuctionStatusResource extends Resource
{
    protected static ?string $model = AuctionStatus::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Estados';
    protected static ?string $pluralLabel = 'Estados de Subasta';
    protected static ?string $modelLabel = 'Estado de Subasta';
    protected static ?string $modelLabelPlural = 'Estados de Subasta';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\ColorPicker::make('background_color')
                        ->label('Color de Fondo')
                        ->required(),

                    Forms\Components\ColorPicker::make('text_color')
                        ->label('Color de Texto')
                        ->required(),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\ColorColumn::make('background_color')
                    ->label('Color de Fondo'),

                Tables\Columns\ColorColumn::make('text_color')
                    ->label('Color de Texto'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
            ])
            ->defaultSort('display_order', 'asc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuctionStatuses::route('/'),
            'edit' => Pages\EditAuctionStatus::route('/{record}/edit'),
        ];
    }
}

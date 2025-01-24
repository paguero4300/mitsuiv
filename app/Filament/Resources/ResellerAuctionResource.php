<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerAuctionResource\Pages;
use App\Models\Auction;
use App\Models\Bid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use App\Services\BidService;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Indicator;
use Carbon\Carbon;
use Filament\Tables\Enums\FiltersLayout;

class ResellerAuctionResource extends Resource
{
    protected static ?string $model = Auction::class;
    protected static ?string $slug = 'reseller-auctions';
    protected static ?string $navigationGroup = 'Revendedor';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $modelLabel = 'Subasta';
    protected static ?string $pluralModelLabel = 'Subastas';
    protected static ?string $navigationLabel = 'Subastas Disponibles';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'vehicle' => function ($query) {
                    $query->with([
                        'brand',
                        'model',
                        'transmission',
                        'bodyType',
                        'cylinders',
                        'fuelType',
                        'doors',
                        'traction',
                        'color',
                        'location',
                        'images',
                        'equipment',
                        'soat_document',
                        'tarjeta_document',
                        'revision_document',
                    ]);
                },
                'status',
            ])
            ->whereIn('status_id', [2, 3])
            ->where('end_date', '>', now());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Placa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.brand.value')
                    ->label('Marca')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.model.value')
                    ->label('Modelo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.year_made')
                    ->label('Año')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Precio Base')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_price')
                    ->label('Precio Actual')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Finaliza')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Auction $record) => 
                        match($record->status_id) {
                            2 => 'warning',
                            3 => 'success',
                            default => 'gray'
                        }
                    ),
                Tables\Columns\TextColumn::make('bid_status')
                    ->label('Mi Puja')
                    ->badge()
                    ->color(fn (Auction $record) => $record->bid_status_color),
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (Auction $record) => $record->canBid()),
            ])
            ->defaultSort('end_date', 'asc')
            ->poll('10s')
            ->filters([
                // Agrupamos los filtros por categorías
                SelectFilter::make('status_id')
                    ->label('Estado')
                    ->multiple()
                    ->preload()
                    ->options(fn() => \App\Models\AuctionStatus::pluck('name', 'id'))
                    ->indicator('Estado'),

                Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('price_from')
                                    ->label('Precio Mínimo')
                                    ->numeric()
                                    ->prefix('$')
                                    ->placeholder('0'),
                                TextInput::make('price_to')
                                    ->label('Precio Máximo')
                                    ->numeric()
                                    ->prefix('$')
                                    ->placeholder('100,000'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn(Builder $query, $price): Builder => $query->where('base_price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn(Builder $query, $price): Builder => $query->where('base_price', '<=', $price),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['price_from'] ?? null) {
                            $indicators[] = Indicator::make('Precio mínimo: $' . number_format($data['price_from']))
                                ->removeField('price_from');
                        }
                        if ($data['price_to'] ?? null) {
                            $indicators[] = Indicator::make('Precio máximo: $' . number_format($data['price_to']))
                                ->removeField('price_to');
                        }
                        return $indicators;
                    }),

                Filter::make('date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')
                                    ->label('Desde')
                                    ->placeholder('Seleccione fecha')
                                    ->closeOnDateSelection(),
                                DatePicker::make('created_until')
                                    ->label('Hasta')
                                    ->placeholder('Seleccione fecha')
                                    ->closeOnDateSelection(),
                            ])
                    ])
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Indicator::make('Desde: ' . Carbon::parse($data['created_from'])->format('d/m/Y'));
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Indicator::make('Hasta: ' . Carbon::parse($data['created_until'])->format('d/m/Y'));
                        }
                        return $indicators;
                    }),

                SelectFilter::make('vehicle_details')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Select::make('brand_id')
                                    ->label('Marca')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn() => \App\Models\CatalogValue::query()
                                        ->join('catalog_types', 'catalog_values.catalog_type_id', '=', 'catalog_types.id')
                                        ->where('catalog_types.name', 'Marca')
                                        ->where('catalog_values.active', true)
                                        ->pluck('catalog_values.value', 'catalog_values.id'))
                                    ->reactive(),
                                Select::make('model_id')
                                    ->label('Modelo')
                                    ->searchable()
                                    ->preload()
                                    ->options(function (callable $get) {
                                        $brandId = $get('brand_id');
                                        return $brandId
                                            ? \App\Models\CatalogValue::where('parent_id', $brandId)
                                                ->where('active', true)
                                                ->pluck('value', 'id')
                                            : [];
                                    })
                                    ->reactive(),
                                TextInput::make('year_made')
                                    ->label('Año')
                                    ->numeric()
                                    ->minValue(1900)
                                    ->maxValue(date('Y'))
                                    ->placeholder('Ej: 2020'),
                                Select::make('location_id')
                                    ->label('Ubicación')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn() => \App\Models\CatalogValue::query()
                                        ->join('catalog_types', 'catalog_values.catalog_type_id', '=', 'catalog_types.id')
                                        ->where('catalog_types.name', 'ubicacion')
                                        ->where('catalog_values.active', true)
                                        ->pluck('catalog_values.value', 'catalog_values.id')),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('vehicle', function (Builder $query) use ($data) {
                            return $query
                                ->when($data['brand_id'], fn($q) => $q->where('brand_id', $data['brand_id']))
                                ->when($data['model_id'], fn($q) => $q->where('model_id', $data['model_id']))
                                ->when($data['year_made'], fn($q) => $q->where('year_made', $data['year_made']))
                                ->when($data['location_id'], fn($q) => $q->where('location_id', $data['location_id']));
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        
                        if ($brandName = \App\Models\CatalogValue::find($data['brand_id'] ?? null)?->value) {
                            $indicators[] = Indicator::make("Marca: {$brandName}");
                        }
                        if ($modelName = \App\Models\CatalogValue::find($data['model_id'] ?? null)?->value) {
                            $indicators[] = Indicator::make("Modelo: {$modelName}");
                        }
                        if ($data['year_made'] ?? null) {
                            $indicators[] = Indicator::make("Año: {$data['year_made']}");
                        }
                        if ($locationName = \App\Models\CatalogValue::find($data['location_id'] ?? null)?->value) {
                            $indicators[] = Indicator::make("Ubicación: {$locationName}");
                        }
                        
                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn ($action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-m-funnel')
            );
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellerAuctions::route('/'),
            'view' => Pages\ViewResellerAuction::route('/{record}'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Nueva sección de precios destacada
                \Filament\Infolists\Components\Section::make()
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('base_price')
                                    ->label('Precio Base')
                                    ->money('USD')
                                    ->size('xl')
                                    ->weight('bold')
                                    ->color('gray')
                                    ->icon('heroicon-o-banknotes'),
                                \Filament\Infolists\Components\TextEntry::make('current_price')
                                    ->label('Precio Actual')
                                    ->money('USD')
                                    ->size('xl')
                                    ->weight('bold')
                                    ->color('success')
                                    ->icon('heroicon-o-currency-dollar'),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('end_date')
                                    ->label('Finaliza en')
                                    ->formatStateUsing(fn ($state) => now()->diff($state)->format('%d días %h horas %i minutos'))
                                    ->icon('heroicon-o-clock')
                                    ->color('warning'),
                                \Filament\Infolists\Components\TextEntry::make('bid_status')
                                    ->label('Estado de mi Puja')
                                    ->badge()
                                    ->color(fn ($record) => $record->bid_status_color)
                                    ->icon(fn ($record) => match($record->bid_status) {
                                        'Puja Líder' => 'heroicon-o-trophy',
                                        'Puja Superada' => 'heroicon-o-arrow-trending-down',
                                        default => 'heroicon-o-minus-circle'
                                    }),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'bg-gray-50 dark:bg-gray-900 border rounded-xl shadow-sm',
                    ]),

                // Galería - Visible por defecto
                \Filament\Infolists\Components\Section::make('Galería del Vehículo')
                    ->description('Imágenes del vehículo seleccionado')
                    ->icon('heroicon-o-camera')
                    ->collapsible()
                    ->persistCollapsed()
                    ->compact()
                    ->extraAttributes([
                        'class' => 'fi-section-content-collapsible',
                    ])
                    ->schema([
                        ViewEntry::make('vehicle_images')
                            ->view('filament.components.vehicle-gallery')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // Información General - Visible por defecto
                \Filament\Infolists\Components\Section::make('Información General')
                    ->description('Detalles técnicos del vehículo')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->persistCollapsed()
                    ->compact()
                    ->extraAttributes([
                        'class' => 'fi-section-content-collapsible',
                    ])
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.plate')
                                    ->label('Placa')
                                    ->icon('heroicon-o-identification')
                                    ->weight('bold')
                                    ->color('primary'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.brand.value')
                                    ->label('Marca')
                                    ->icon('heroicon-o-building-storefront')
                                    ->weight('bold'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.model.value')
                                    ->label('Modelo')
                                    ->icon('heroicon-o-truck')
                                    ->weight('bold'),
                            ]),

                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.year_made')
                                    ->label('Año Fabricación')
                                    ->icon('heroicon-o-calendar')
                                    ->badge(),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.model_year')
                                    ->label('Año Modelo')
                                    ->icon('heroicon-o-calendar')
                                    ->badge(),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.mileage')
                                    ->label('Kilometraje')
                                    ->icon('heroicon-o-map')
                                    ->badge()
                                    ->formatStateUsing(fn($state) => number_format($state) . ' km'),
                            ]),

                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.transmission.value')
                                    ->label('Transmisión')
                                    ->icon('heroicon-o-cog-6-tooth'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.bodyType.value')
                                    ->label('Tipo de Carrocería')
                                    ->icon('heroicon-o-cube'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.traction.value')
                                    ->label('Tracción')
                                    ->icon('heroicon-o-wrench'),
                            ]),

                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.engine_cc')
                                    ->label('Cilindrada')
                                    ->icon('heroicon-o-beaker')
                                    ->formatStateUsing(fn($state) => number_format($state) . ' cc'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.cylinders.value')
                                    ->label('Cilindros')
                                    ->icon('heroicon-o-variable'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.fuelType.value')
                                    ->label('Combustible')
                                    ->icon('heroicon-o-fire'),
                            ]),

                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.doors.value')
                                    ->label('Puertas')
                                    ->icon('heroicon-o-swatch'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.color.value')
                                    ->label('Color')
                                    ->icon('heroicon-o-swatch'),
                                \Filament\Infolists\Components\TextEntry::make('vehicle.location.value')
                                    ->label('Ubicación')
                                    ->icon('heroicon-o-map-pin'),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Descripción Adicional - Oculto por defecto
                \Filament\Infolists\Components\Section::make('Descripción Adicional')
                    ->description('Detalles y observaciones adicionales')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->compact()
                    ->extraAttributes([
                        'class' => 'fi-section-content-collapsible',
                    ])
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('vehicle.additional_description')
                            ->label('Descripción')
                            ->icon('heroicon-o-document-text')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // Equipamiento y Características - Oculto por defecto
                \Filament\Infolists\Components\Section::make('Equipamiento y Características')
                    ->description('Características y equipamiento detallado del vehículo')
                    ->icon('heroicon-o-cog')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->compact()
                    ->extraAttributes([
                        'class' => 'fi-section-content-collapsible',
                    ])
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.equipment.airbags_count')
                                    ->label('Airbags')
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn($state) => $state . ' unidades'),
                            ])
                            ->columnSpanFull(),

                        self::createEquipmentGrid([
                            'alarm' => 'Alarma',
                            'abs_ebs' => 'ABS/EBS',
                            'security_glass' => 'Láminas de Seguridad',
                            'anti_collision' => 'Sistema Anti Colisión',
                        ]),

                        self::createEquipmentGrid([
                            'air_conditioning' => 'Aire Acondicionado',
                            'mono_zone_ac' => 'AC Mono-zona',
                            'bi_zone_ac' => 'AC Bi-zona',
                            'multi_zone_ac' => 'AC Multi-zona',
                            'electric_seats' => 'Asientos Eléctricos',
                            'leather_seats' => 'Asientos de Cuero',
                            'sunroof' => 'Techo Solar',
                            'cruise_control' => 'Control Crucero',
                            'electric_mirrors' => 'Retrovisores Eléctricos',
                        ]),

                        self::createEquipmentGrid([
                            'apple_carplay' => 'Apple CarPlay',
                            'touch_screen' => 'Pantalla Táctil',
                            'gps' => 'GPS',
                            'speakers' => 'Sistema de Audio',
                            'steering_controls' => 'Controles al Volante',
                            'usb_ports' => 'Puertos USB',
                            'cd_player' => 'Reproductor CD',
                            'mp3_player' => 'Reproductor MP3',
                        ]),

                        self::createEquipmentGrid([
                            'front_fog_lights' => 'Faros Antiniebla Delanteros',
                            'rear_fog_lights' => 'Faros Antiniebla Traseros',
                            'bi_led_lights' => 'Luces Bi-LED',
                            'halogen_lights' => 'Luces Halógenas',
                            'led_lights' => 'Luces LED',
                        ]),

                        self::createEquipmentGrid([
                            'front_camera' => 'Cámara Frontal',
                            'rear_camera' => 'Cámara Trasera',
                            'right_camera' => 'Cámara Derecha',
                            'left_camera' => 'Cámara Izquierda',
                        ]),

                        self::createEquipmentGrid([
                            'wheels' => 'Ruedas',
                            'alloy_wheels' => 'Aros de Aleación',
                            'roof_rack' => 'Barras de Techo',
                            'parking_sensors' => 'Sensores de Estacionamiento',
                        ]),

                        self::createEquipmentGrid([
                            'factory_warranty' => 'Garantía de Fábrica',
                            'complete_documentation' => 'Documentación Completa',
                            'guaranteed_mileage' => 'Kilometraje Garantizado',
                            'financing' => 'Financiamiento Disponible',
                            'part_payment' => 'Acepta Parte de Pago',
                        ]),
                    ])
                    ->columnSpanFull(),

                // Documentos del Vehículo - Oculto por defecto
                \Filament\Infolists\Components\Section::make('Documentos del Vehículo')
                    ->description('Documentación legal del vehículo')
                    ->icon('heroicon-o-document-duplicate')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->compact()
                    ->extraAttributes([
                        'class' => 'fi-section-content-collapsible',
                    ])
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.soat_document.path')
                                    ->label('SOAT')
                                    ->icon('heroicon-o-document-check')
                                    ->formatStateUsing(function ($state) {
                                        if (!$state) return null;
                                        return view('filament.components.document-download', [
                                            'url' => Storage::url($state),
                                            'label' => 'SOAT'
                                        ]);
                                    })
                                    ->html()
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                                \Filament\Infolists\Components\TextEntry::make('vehicle.tarjeta_document.path')
                                    ->label('Tarjeta de Propiedad')
                                    ->icon('heroicon-o-document-check')
                                    ->formatStateUsing(function ($state) {
                                        if (!$state) return null;
                                        return view('filament.components.document-download', [
                                            'url' => Storage::url($state),
                                            'label' => 'Tarjeta'
                                        ]);
                                    })
                                    ->html()
                                    ->badge(),

                                \Filament\Infolists\Components\TextEntry::make('vehicle.revision_document.path')
                                    ->label('Revisión Técnica')
                                    ->icon('heroicon-o-document-check')
                                    ->formatStateUsing(function ($state) {
                                        if (!$state) return null;
                                        return view('filament.components.document-download', [
                                            'url' => Storage::url($state),
                                            'label' => 'Revisión'
                                        ]);
                                    })
                                    ->html()
                                    ->badge(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function createEquipmentGrid(array $items)
    {
        return \Filament\Infolists\Components\Grid::make(4)
            ->schema(
                collect($items)->map(
                    fn($label, $field) =>
                    \Filament\Infolists\Components\IconEntry::make('vehicle.equipment.' . $field)
                        ->label($label)
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger')
                        ->size('sm')
                )->toArray()
            );
    }
}
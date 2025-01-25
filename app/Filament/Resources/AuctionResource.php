<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionResource\Pages;
use App\Models\Auction;
use App\Models\AdminAuction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Get;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Filament\Tables\Filters\Indicator;
use Carbon\Carbon;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\InfoList;
use Filament\Infolists\Components\ViewEntry;
use Hydrat\TableLayoutToggle\Concerns\HasToggleableTable;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Card;
use Filament\Infolists\Components\Actions\Action;
use Filament\Actions\Action as FilamentAction;
use Illuminate\Support\HtmlString;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Closure;


class AuctionResource extends Resource
{
    protected static ?string $model = AdminAuction::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Subastas';
    protected static ?string $pluralLabel = 'Subastas';
    protected static ?string $pluralModelLabel = 'Subastas';
    protected static ?string $modelLabel = 'Subasta';
    protected static ?string $recordTitleAttribute = 'Subasta';
    protected static ?string $navigationGroup = 'Configuración Subastas';
    protected static ?string $slug = 'admin-auctions';


    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nueva Subasta')
                    ->description('Complete los campos requeridos para crear la subasta')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Placeholder::make('current_time')
                            ->label('Fecha y Hora Actual del Sistema')
                            ->content(function () {
                                return view('filament.components.current-time', [
                                    'time' => now()->timezone('America/Lima')->format('d/m/Y H:i:s')
                                ]);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('vehicle_id')
                            ->label('Vehículo')
                            ->relationship('vehicle', 'plate')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Seleccione el vehículo que desea subastar')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Card::make()
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('start_date')
                                            ->label('Fecha y Hora de Inicio')
                                            ->required()
                                            ->native(false)
                                            ->timezone('America/Lima')
                                            ->format('Y-m-d H:i')
                                            ->displayFormat('d/m/Y H:i')
                                            ->firstDayOfWeek(1)
                                            ->minDate(now()->timezone('America/Lima')->format('Y-m-d'))
                                            ->helperText('La fecha y hora de inicio debe ser igual o posterior a la hora actual')
                                            ->live()
                                            ->rules([
                                                'required',
                                                'date',
                                                'after_or_equal:' . now()->timezone('America/Lima')->format('Y-m-d H:i'),
                                            ]),

                                        Forms\Components\DateTimePicker::make('end_date')
                                            ->label('Fecha y Hora de Fin')
                                            ->required()
                                            ->native(false)
                                            ->timezone('America/Lima')
                                            ->format('Y-m-d H:i')
                                            ->displayFormat('d/m/Y H:i')
                                            ->firstDayOfWeek(1)
                                            ->minDate(now()->timezone('America/Lima')->format('Y-m-d'))
                                            ->helperText('La hora de fin debe ser mayor a la hora de inicio')
                                            ->live()
                                            ->rules([
                                                'required',
                                                'date',
                                                function ($get) {
                                                    return function (string $attribute, $value, Closure $fail) use ($get) {
                                                        $startDate = Carbon::parse($get('start_date'))->timezone('America/Lima');
                                                        $endDate = Carbon::parse($value)->timezone('America/Lima');
                                                        
                                                        if ($endDate->format('Y-m-d') === $startDate->format('Y-m-d')) {
                                                            if ($endDate->format('H:i') <= $startDate->format('H:i')) {
                                                                $fail("La hora de fin debe ser mayor a la hora de inicio ({$startDate->format('H:i')})");
                                                            }
                                                        } elseif ($endDate < $startDate) {
                                                            $fail("La fecha de fin no puede ser anterior a la fecha de inicio");
                                                        }
                                                    };
                                                }
                                            ]),
                                    ]),

                                Forms\Components\Card::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('base_price')
                                            ->label('Precio Base')
                                            ->required()
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(1)
                                            ->maxValue(999999999)
                                            ->step(0.01)
                                            ->inputMode('decimal')
                                            ->helperText('Ingrese el precio base de la subasta (mínimo $1)')
                                            ->columnSpanFull(),

                                        Forms\Components\Placeholder::make('duration_preview')
                                            ->label('Duración de la Subasta')
                                            ->content(function ($get) {
                                                $startDate = $get('start_date');
                                                $endDate = $get('end_date');

                                                if (!$startDate || !$endDate) {
                                                    return view('filament.components.duration-preview', [
                                                        'message' => 'Seleccione las fechas de inicio y fin para ver la duración',
                                                        'duration' => null
                                                    ]);
                                                }

                                                try {
                                                    $start = Carbon::parse($startDate)->timezone('America/Lima');
                                                    $end = Carbon::parse($endDate)->timezone('America/Lima');

                                                    if ($end <= $start) {
                                                        return view('filament.components.duration-preview', [
                                                            'message' => 'La fecha de fin debe ser posterior a la fecha de inicio',
                                                            'duration' => null
                                                        ]);
                                                    }

                                                    $interval = $start->diff($end);
                                                    $durationParts = [];

                                                    if ($interval->d > 0) {
                                                        $durationParts[] = "{$interval->d} día(s)";
                                                    }
                                                    if ($interval->h > 0) {
                                                        $durationParts[] = "{$interval->h} hora(s)";
                                                    }
                                                    if ($interval->i > 0) {
                                                        $durationParts[] = "{$interval->i} minuto(s)";
                                                    }

                                                    if (empty($durationParts)) {
                                                        $durationParts[] = "menos de 1 minuto";
                                                    }

                                                    return view('filament.components.duration-preview', [
                                                        'message' => 'La subasta durará:',
                                                        'duration' => implode(', ', $durationParts)
                                                    ]);
                                                } catch (\InvalidArgumentException $e) {
                                                    logger()->error('Error al calcular duración de subasta: ' . $e->getMessage());
                                                    return view('filament.components.duration-preview', [
                                                        'message' => 'Error al calcular la duración',
                                                        'duration' => null
                                                    ]);
                                                }
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Hidden::make('appraiser_id')
                            ->default(fn() => Auth::id()),

                        Forms\Components\Hidden::make('status_id')
                            ->default(fn() => \App\Models\AuctionStatus::where('slug', 'sin-oferta')->first()?->id ?? 2),

                        Forms\Components\Hidden::make('duration_hours')
                            ->default(fn (Forms\Get $get): int => 
                                Carbon::parse($get('end_date'))->diffInHours(Carbon::parse($get('start_date')))
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }


    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
        ->schema([
            // Información de Precios y Tiempo
            \Filament\Infolists\Components\Section::make('Información de Precios y Tiempo')
                ->schema([
                    \Filament\Infolists\Components\Grid::make(4)
                        ->schema([
                            TextEntry::make('start_countdown')
                                ->label('Inicio')
                                ->state(function (Auction $record) {
                                    $now = now()->timezone('America/Lima');
                                    $start = Carbon::parse($record->start_date)->timezone('America/Lima');

                                    if ($start->lte($now)) {
                                        return null;
                                    }

                                    $interval = $now->diff($start);
                                    $parts = [];

                                    if ($interval->d > 0) {
                                        $parts[] = "{$interval->d}d";
                                    }
                                    if ($interval->h > 0) {
                                        $parts[] = "{$interval->h}h";
                                    }
                                    if ($interval->i > 0) {
                                        $parts[] = "{$interval->i}m";
                                    }

                                    return "Inicia en: " . (empty($parts) ? "< 1m" : implode(' ', $parts));
                                })
                                ->badge()
                                ->color('warning')
                                ->weight('bold')
                                ->visible(fn (Auction $record) => Carbon::parse($record->start_date)->gt(now())),

                            TextEntry::make('remaining_time')
                                ->label('Tiempo Restante')
                                ->state(function (Auction $record) {
                                    $now = now()->timezone('America/Lima');
                                    $start = Carbon::parse($record->start_date)->timezone('America/Lima');
                                    $end = Carbon::parse($record->end_date)->timezone('America/Lima');

                                    // Si no ha iniciado, no mostramos nada
                                    if ($start->gt($now)) {
                                        return null;
                                    }

                                    // Si ya finalizó
                                    if ($end->isPast()) {
                                        return 'Subasta finalizada';
                                    }

                                    // Si está en curso, mostramos tiempo restante
                                    $interval = $now->diff($end);
                                    $parts = [];

                                    if ($interval->d > 0) {
                                        $parts[] = "{$interval->d}d";
                                    }
                                    if ($interval->h > 0) {
                                        $parts[] = "{$interval->h}h";
                                    }
                                    if ($interval->i > 0) {
                                        $parts[] = "{$interval->i}m";
                                    }

                                    return empty($parts) ? "< 1m" : implode(' ', $parts);
                                })
                                ->badge()
                                ->color(function (Auction $record) {
                                    $now = now()->timezone('America/Lima');
                                    $start = Carbon::parse($record->start_date)->timezone('America/Lima');
                                    $end = Carbon::parse($record->end_date)->timezone('America/Lima');

                                    if ($start->gt($now) || $end->isPast()) {
                                        return 'gray';
                                    }

                                    $hoursRemaining = $now->diffInHours($end, false);
                                    
                                    if ($hoursRemaining <= 1) {
                                        return 'danger';
                                    } elseif ($hoursRemaining <= 6) {
                                        return 'warning';
                                    } elseif ($hoursRemaining <= 24) {
                                        return 'info';
                                    } else {
                                        return 'success';
                                    }
                                })
                                ->weight('bold')
                                ->visible(fn (Auction $record) => 
                                    Carbon::parse($record->start_date)->lte(now()) && 
                                    Carbon::parse($record->end_date)->gt(now())
                                ),

                            TextEntry::make('base_price')
                                ->label('Precio Base')
                                ->prefix('US$')
                                ->numeric(
                                    decimalPlaces: 2,
                                    thousandsSeparator: ',',
                                )
                                ->badge()
                                ->color('info')
                                ->weight('bold'),
                                
                            TextEntry::make('current_price')
                                ->label('Precio Final')
                                ->prefix('US$')
                                ->numeric(
                                    decimalPlaces: 2,
                                    thousandsSeparator: ',',
                                )
                                ->badge()
                                ->color('success')
                                ->weight('bold'),

                            \Filament\Infolists\Components\Actions::make([
                                \Filament\Infolists\Components\Actions\Action::make('ver_historial')
                                    ->label('Ver Historial')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('primary')
                                    ->modalHeading('Historial de Ofertas')
                                    ->modalIcon('heroicon-o-banknotes')
                                    ->modalWidth('xl')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Cancelar')
                                    ->before(function() {
                                        return new HtmlString("
                                            <script>
                                                document.addEventListener('modal-opened', function() {
                                                    const gallery = document.querySelector('.fi-section:has([data-section-content=\"Galería del Vehículo\"])');
                                                    if (gallery) {
                                                        gallery.style.setProperty('visibility', 'hidden', 'important');
                                                        gallery.style.setProperty('position', 'absolute', 'important');
                                                        gallery.style.setProperty('pointer-events', 'none', 'important');
                                                        gallery.style.setProperty('z-index', '-1', 'important');
                                                    }
                                                });
                                                
                                                document.addEventListener('modal-closed', function() {
                                                    const gallery = document.querySelector('.fi-section:has([data-section-content=\"Galería del Vehículo\"])');
                                                    if (gallery) {
                                                        gallery.style.removeProperty('visibility');
                                                        gallery.style.removeProperty('position');
                                                        gallery.style.removeProperty('pointer-events');
                                                        gallery.style.removeProperty('z-index');
                                                    }
                                                });
                                            </script>
                                        ");
                                    })
                                    ->modalContent(function (Auction $record) {
                                        return view('filament.components.bid-history', [
                                            'bids' => $record->bids()->with('reseller')->get()
                                        ]);
                                    })
                                    ->closeModalByClickingAway(false),

                                \Filament\Infolists\Components\Actions\Action::make('ver_ganador')
                                    ->label('Ver Ganador')
                                    ->icon('heroicon-o-trophy')
                                    ->color('success')
                                    ->visible(fn (Auction $record) => $record->end_date->isPast())
                                    ->modalHeading('Ganador de la Subasta')
                                    ->modalIcon('heroicon-o-trophy')
                                    ->modalWidth('md')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Cerrar')
                                    ->modalContent(function (Auction $record) {
                                        $winningBid = $record->bids()->orderByDesc('amount')->first();
                                        
                                        if (!$winningBid) {
                                            return new HtmlString('
                                                <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                                                    <x-heroicon-o-x-circle class="w-12 h-12 mb-3 text-danger-500" />
                                                    <p class="text-lg font-medium">Subasta sin ganador</p>
                                                    <p class="text-sm text-gray-400">No se registraron ofertas en esta subasta</p>
                                                </div>
                                            ');
                                        }

                                        return new HtmlString('
                                            <div class="p-4 space-y-6">
                                                <div class="flex items-center justify-center">
                                                    <div class="p-2 rounded-full bg-success-50">
                                                        <x-heroicon-o-trophy class="w-8 h-8 text-success-500" />
                                                    </div>
                                                </div>
                                                
                                                <div class="space-y-4">
                                                    <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                                        <div class="flex items-center gap-4">
                                                            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-50">
                                                                <x-heroicon-o-user class="w-6 h-6 text-primary-500" />
                                                            </div>
                                                            <div>
                                                                <h3 class="text-lg font-semibold text-gray-900">' . e($winningBid->reseller->name) . '</h3>
                                                                <p class="text-sm text-gray-500">' . e($winningBid->reseller->email) . '</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div class="p-4 rounded-lg bg-gray-50">
                                                            <p class="text-sm font-medium text-gray-500">Monto Ganador</p>
                                                            <p class="text-lg font-bold text-success-600">US$ ' . number_format($winningBid->amount, 2) . '</p>
                                                        </div>
                                                        <div class="p-4 rounded-lg bg-gray-50">
                                                            <p class="text-sm font-medium text-gray-500">Fecha de Adjudicación</p>
                                                            <p class="text-lg font-medium text-gray-700">' . $record->end_date->format('d/m/Y H:i') . '</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ');
                                    })
                            ])
                        ])
                ]), 
                // Galería del Vehículo
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

                // Información General
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

                // Descripción Adicional
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

                // Equipamiento y Características
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

                        \Filament\Infolists\Components\Section::make('Seguridad')
                            ->schema([
                                self::createEquipmentGrid([
                                    'alarm' => 'Alarma',
                                    'abs_ebs' => 'ABS/EBS',
                                    'security_glass' => 'Láminas de Seguridad',
                                    'anti_collision' => 'Sistema Anti Colisión',
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->compact(),

                            \Filament\Infolists\Components\Section::make('Confort')
                                ->schema([
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
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                            \Filament\Infolists\Components\Section::make('Multimedia')
                                ->schema([
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
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                            \Filament\Infolists\Components\Section::make('Iluminación')
                                ->schema([
                                    self::createEquipmentGrid([
                                        'front_fog_lights' => 'Faros Antiniebla Delanteros',
                                        'rear_fog_lights' => 'Faros Antiniebla Traseros',
                                        'bi_led_lights' => 'Luces Bi-LED',
                                        'halogen_lights' => 'Luces Halógenas',
                                        'led_lights' => 'Luces LED',
                                    ]),
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                            \Filament\Infolists\Components\Section::make('Cámaras')
                                ->schema([
                                    self::createEquipmentGrid([
                                        'front_camera' => 'Cámara Frontal',
                                        'rear_camera' => 'Cámara Trasera',
                                        'right_camera' => 'Cámara Derecha',
                                        'left_camera' => 'Cámara Izquierda',
                                    ]),
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                            \Filament\Infolists\Components\Section::make('Otros')
                                ->schema([
                                    self::createEquipmentGrid([
                                        'wheels' => 'Ruedas',
                                        'alloy_wheels' => 'Aros de Aleación',
                                        'roof_rack' => 'Barras de Techo',
                                        'parking_sensors' => 'Sensores de Estacionamiento',
                                    ]),
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                            \Filament\Infolists\Components\Section::make('Garantías y Financiamiento')
                                ->schema([
                                    self::createEquipmentGrid([
                                        'factory_warranty' => 'Garantía de Fábrica',
                                        'complete_documentation' => 'Documentación Completa',
                                        'guaranteed_mileage' => 'Kilometraje Garantizado',
                                        'financing' => 'Financiamiento Disponible',
                                        'part_payment' => 'Acepta Parte de Pago',
                                    ]),
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->compact(),

                    ])
                    ->columnSpanFull(),

                // Documentos del Vehículo
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


    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'vehicle',
                'vehicle.brand',
                'vehicle.model',
                'vehicle.images',
                'status'
            ]))
            ->columns([
                Stack::make([
                    Tables\Columns\ImageColumn::make('vehicle.images')
                        ->height(200)
                        ->width('100%')
                        ->defaultImageUrl(url('/images/vehiculo.png'))
                        ->state(fn($record) => optional($record->vehicle->images()->where('is_main', true)->first())->path)
                        ->alignment('center')
                        ->extraImgAttributes([
                            'class' => 'w-full h-[200px] object-cover rounded-t-xl',
                        ]),

                    Tables\Columns\Layout\Grid::make([
                        'default' => 2,
                        'sm' => 2,
                    ])
                    ->schema([
                        Tables\Columns\TextColumn::make('start_date')
                            ->label('Fecha')
                            ->formatStateUsing(function (Auction $record): string {
                                $start = Carbon::parse($record->start_date)->timezone('America/Lima');
                                $now = now()->timezone('America/Lima');
                                
                                $dateStr = $start->format('d/m/Y');
                                
                                if ($start->gt($now)) {
                                    $interval = $now->diff($start);
                                    $parts = [];
                                    
                                    if ($interval->d > 0) {
                                        $parts[] = "{$interval->d}d";
                                    }
                                    if ($interval->h > 0) {
                                        $parts[] = "{$interval->h}h";
                                    }
                                    if ($interval->i > 0) {
                                        $parts[] = "{$interval->i}m";
                                    }
                                    if ($interval->s > 0) {
                                        $parts[] = "{$interval->s}s";
                                    }
                                    
                                    $timeLeft = empty($parts) ? "< 1s" : implode(' ', $parts);
                                    return "{$dateStr} (Inicia en: {$timeLeft})";
                                }
                                
                                return $dateStr;
                            })
                            ->badge()
                            ->color(function (Auction $record) {
                                $start = Carbon::parse($record->start_date)->timezone('America/Lima');
                                $now = now()->timezone('America/Lima');
                                
                                if ($start->gt($now)) {
                                    $hoursRemaining = $now->diffInHours($start, false);
                                    
                                    if ($hoursRemaining <= 1) {
                                        return 'danger';
                                    } elseif ($hoursRemaining <= 6) {
                                        return 'warning';
                                    } elseif ($hoursRemaining <= 24) {
                                        return 'info';
                                    } else {
                                        return 'success';
                                    }
                                }
                                
                                return null;
                            })
                            ->extraAttributes([
                                'class' => 'text-gray-600 font-medium',
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'gap-2 items-center',
                    ]),

                    Tables\Columns\Layout\Grid::make()
                        ->schema([
                            Tables\Columns\TextColumn::make('vehicle.plate')
                                ->label('Placa')
                                ->formatStateUsing(fn ($state): string => "Placa: {$state}")
                                ->extraAttributes([
                                    'class' => 'text-lg font-bold text-primary-600',
                                ]),

                            Tables\Columns\TextColumn::make('vehicle.brand.value')
                                ->label('Marca')
                                ->formatStateUsing(fn ($state): string => "Marca: {$state}")
                                ->extraAttributes([
                                    'class' => 'text-gray-600',
                                ]),

                            Tables\Columns\TextColumn::make('vehicle.model.value')
                                ->label('Modelo')
                                ->formatStateUsing(fn ($state): string => "Modelo: {$state}")
                                ->extraAttributes([
                                    'class' => 'text-gray-800',
                                ]),

                            Tables\Columns\TextColumn::make('vehicle.year_made')
                                ->label('Año')
                                ->formatStateUsing(fn ($state): string => "Año: {$state}")
                                ->extraAttributes([
                                    'class' => 'text-gray-600',
                                ]),

                            Tables\Columns\TextColumn::make('base_price')
                                ->label('Base')
                                ->formatStateUsing(fn ($state): string => "Base: $ " . number_format($state ?? 0, 2))
                                ->extraAttributes([
                                    'class' => 'text-success-600 font-bold',
                                ]),

                            Tables\Columns\TextColumn::make('current_price')
                                ->label('Actual')
                                ->formatStateUsing(fn ($state): string => "Actual: $ " . number_format($state ?? 0, 2))
                                ->extraAttributes([
                                    'class' => 'text-primary-600 font-bold',
                                ]),

                            Tables\Columns\TextColumn::make('end_date')
                                ->formatStateUsing(function (Auction $record) {
                                    $now = now()->timezone('America/Lima');
                                    $startDate = Carbon::parse($record->start_date)->timezone('America/Lima');
                                    $endDate = Carbon::parse($record->end_date)->timezone('America/Lima');

                                    if ($startDate->gt($now)) {
                                        return null;
                                    }

                                    if ($endDate->isPast()) {
                                        return "Tiempo: Finalizada";
                                    }

                                    $remaining = $now->diff($endDate);
                                    
                                    if ($remaining->days > 0) {
                                        return "Tiempo: {$remaining->days}d {$remaining->h}h {$remaining->i}m {$remaining->s}s";
                                    } elseif ($remaining->h > 0) {
                                        return "Tiempo: {$remaining->h}h {$remaining->i}m {$remaining->s}s";
                                    } elseif ($remaining->i > 0) {
                                        return "Tiempo: {$remaining->i}m {$remaining->s}s";
                                    } else {
                                        return "Tiempo: {$remaining->s}s";
                                    }
                                })
                                ->color(function (Auction $record) {
                                    $now = now()->timezone('America/Lima');
                                    $startDate = Carbon::parse($record->start_date)->timezone('America/Lima');
                                    $endDate = Carbon::parse($record->end_date)->timezone('America/Lima');

                                    if ($startDate->gt($now) || $endDate->isPast()) {
                                        return null;
                                    }
                                    
                                    $hoursRemaining = $now->diffInHours($endDate, false);
                                    
                                    if ($hoursRemaining <= 1) {
                                        return 'danger';
                                    } elseif ($hoursRemaining <= 6) {
                                        return 'warning';
                                    } elseif ($hoursRemaining <= 24) {
                                        return 'info';
                                    } else {
                                        return 'success';
                                    }
                                })
                                ->extraAttributes([
                                    'class' => 'text-gray-600 font-medium',
                                ]),

                            Tables\Columns\TextColumn::make('status.name')
                                ->formatStateUsing(fn (string $state): string => "Estado: {$state}")
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Sin Oferta' => 'danger',
                                    'En Proceso' => 'warning',
                                    'Finalizada' => 'success',
                                    default => 'gray'
                                })
                                ->extraAttributes([
                                    'class' => 'text-sm',
                                ]),
                        ])
                        ->columns([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                        ])
                        ->extraAttributes([
                            'class' => 'gap-y-2 p-4',
                        ]),
                ])
                ->space(3)
                ->extraAttributes([
                    'class' => 'rounded-xl shadow-sm',
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->bulkActions([])
            ->filters([
                SelectFilter::make('appraiser_id')
                    ->label('Tasador')
                    ->placeholder('Tasador...')
                    ->options(function () {
                        return \App\Models\User::query()
                            ->join('model_has_roles', function ($join) {
                                $join->on('users.id', '=', 'model_has_roles.model_id')
                                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                            })
                            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->where('roles.name', '=', 'tasador')
                            ->pluck('users.name', 'users.id');
                    })
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')
                                    ->label('Desde')
                                    ->placeholder('Seleccione fecha')
                                    ->closeOnDateSelection(),
                                DatePicker::make('created_until')
                                    ->label('Hasta')
                                    ->placeholder('Seleccione fecha')
                                    ->closeOnDateSelection(),
                            ]),
                    ])
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

                SelectFilter::make('status_id')
                    ->multiple()
                    ->label('Estado')
                    ->placeholder('Estado...')
                    ->preload()
                    ->options(fn() => \App\Models\AuctionStatus::pluck('name', 'id'))
                    ->indicator('Estado'),

                TernaryFilter::make('active')
                    ->label('Subastas')
                    ->placeholder('Subastas...')
                    ->queries(
                        true: fn (Builder $query) => $query->where('end_date', '>', now()),
                        false: fn (Builder $query) => $query->where('end_date', '<=', now()),
                    )
                    ->trueLabel('Activas')
                    ->falseLabel('Finalizadas')
                    ->native(false),

                Filter::make('vehicle_details')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                Select::make('brand_id')
                                    ->label('Marca')
                                    ->placeholder('Marca...')
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
                                    ->placeholder('Modelo...')
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
                                Select::make('location_id')
                                    ->label('Ubicación')
                                    ->placeholder('Ubicación...')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn() => \App\Models\CatalogValue::query()
                                        ->join('catalog_types', 'catalog_values.catalog_type_id', '=', 'catalog_types.id')
                                        ->where('catalog_types.name', 'ubicacion')
                                        ->where('catalog_values.active', true)
                                        ->pluck('catalog_values.value', 'catalog_values.id')),
                                TextInput::make('year_made')
                                    ->label('Año')
                                    ->numeric()
                                    ->minValue(1900)
                                    ->maxValue(date('Y'))
                                    ->placeholder('Ej: 2020'),
                            ]),
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

                Filter::make('price_range')
                    ->form([
                        Grid::make(2)
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
                            ]),
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
                            $indicators[] = Indicator::make('Precio mínimo: $' . number_format($data['price_from']));
                        }
                        if ($data['price_to'] ?? null) {
                            $indicators[] = Indicator::make('Precio máximo: $' . number_format($data['price_to']));
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn ($action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-m-funnel')
                    ->size('sm')
                    ->color('gray')
            )

            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(Auction $record): string => static::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-magnifying-glass'),

                Tables\Actions\EditAction::make()
                    ->hidden(fn (Model $record): bool => $record->appraiser_id !== Auth::id()),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Model $record): bool => $record->appraiser_id !== Auth::id()),
            ])
            ->bulkActions([
               
            ])
            ->defaultSort('start_date', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            // Define aquí cualquier relación adicional, si es necesario
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuctions::route('/'),
            'create' => Pages\CreateAuction::route('/create'),
            'edit' => Pages\EditAuction::route('/{record}/edit'),
            'view' => Pages\ViewAuction::route('/{record}/view')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'vehicle',
                'vehicle.brand',
                'vehicle.model',
                'vehicle.images',
                'status',
                'bids',
                'bids.reseller'
            ]);
    }
}

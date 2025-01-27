<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\RelationManagers\ImagesRelationManager;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Vehículo';
    protected static ?string $pluralLabel = 'Vehículos';
    protected static ?string $navigationGroup = 'Subastas y Vehículos';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                Wizard\Step::make('Información General')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->description('Datos principales del vehículo')
                    ->schema([
                        // Sección: Información General del Vehículo
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('plate')
                                ->label('Placa')
                                ->placeholder('Formato: ABC-123')
                                ->required('El campo placa es obligatorio')
                                ->unique(table: 'vehicles', column: 'plate', ignoreRecord: true)
                                ->maxLength(7, 'La placa no debe exceder los 7 caracteres')
                                ->regex('/^[A-Z0-9]{1,3}-[A-Z0-9]{1,3}$/', 'El formato de placa debe ser XXX-XXX')
                                ->helperText('La placa debe tener el formato XXX-XXX (letras o números separados por guión)')
                                ->prefixIcon('heroicon-o-identification')
                                ->formatStateUsing(fn ($state) => strtoupper($state))
                                ->mask('***-***'),

                            Forms\Components\Select::make('brand_id')
                                ->label('Marca')
                                ->relationship(
                                    name: 'brand',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) =>
                                        $query->whereHas('type', fn($q) => $q->where('name', 'marca'))
                                )
                                ->required('El campo marca es obligatorio')
                                ->placeholder('Seleccione una marca')
                                ->prefixIcon('heroicon-o-building-storefront')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('value')
                                        ->label('Nombre de la Marca')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                    return $action
                                        ->button()
                                        ->color('primary')
                                        ->label('Crear')
                                        ->modalHeading('Crear Nueva Marca')
                                        ->modalSubmitActionLabel('Crear Marca')
                                        ->modalWidth('lg');
                                })
                                ->createOptionUsing(function (array $data) {
                                    $data['catalog_type_id'] = \App\Models\CatalogType::where('name', 'marca')->first()?->id;
                                    $data['active'] = true;
                                    return \App\Models\CatalogValue::create($data)->getKey();
                                })
                                ->afterStateUpdated(fn (callable $set) => $set('model_id', null))
                                ->reactive(),

                            Forms\Components\Select::make('model_id')
                                ->label('Modelo')
                                ->relationship(
                                    name: 'model',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query, $get) =>
                                        $query->whereHas('type', fn($q) => $q->where('name', 'modelo'))
                                            ->when(
                                                $get('brand_id'),
                                                fn ($query, $brandId) => $query->where('parent_id', $brandId)
                                            )
                                )
                                ->required('El campo modelo es obligatorio')
                                ->placeholder('Seleccione un modelo')
                                ->prefixIcon('heroicon-o-tag')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->createOptionForm([
                                    Forms\Components\Select::make('parent_id')
                                        ->label('Marca')
                                        ->options(fn () => \App\Models\CatalogValue::whereHas('type', fn($q) => $q->where('name', 'marca'))->pluck('value', 'id'))
                                        ->required(),
                                    Forms\Components\TextInput::make('value')
                                        ->label('Nombre del Modelo')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                    return $action
                                        ->button()
                                        ->color('primary')
                                        ->label('Crear')
                                        ->modalHeading('Crear Nuevo Modelo')
                                        ->modalSubmitActionLabel('Crear Modelo')
                                        ->modalWidth('lg');
                                })
                                ->createOptionUsing(function (array $data) {
                                    $data['catalog_type_id'] = \App\Models\CatalogType::where('name', 'modelo')->first()?->id;
                                    $data['active'] = true;
                                    return \App\Models\CatalogValue::create($data)->getKey();
                                })
                                ->disabled(fn($get) => ! $get('brand_id')),

                            Forms\Components\TextInput::make('version')
                                ->label('Versión')
                                ->placeholder('Ingrese la versión del vehículo')
                                ->required()
                                ->maxLength(255)
                                ->prefixIcon('heroicon-o-code-bracket'),

                            Forms\Components\Select::make('transmission_id')
                                ->label('Transmisión')
                                ->relationship(
                                    name: 'transmission',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'transmision'))
                                )
                                ->prefixIcon('heroicon-o-cog-6-tooth')
                                ->required('El campo transmisión es obligatorio')
                                ->placeholder('Seleccione una transmisión')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('body_type_id')
                                ->label('Carrocería')
                                ->relationship(
                                    name: 'bodyType',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'carroceria'))
                                )
                                ->prefixIcon('heroicon-o-cube')
                                ->required('El campo carrocería es obligatorio')
                                ->placeholder('Seleccione una carrocería')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('year_made')
                                ->label('Año de Fabricación')
                                ->options(function() {
                                    $currentYear = (int)date('Y');
                                    $years = range($currentYear, 1900);
                                    return array_combine($years, $years);
                                })
                                ->required()
                                ->searchable()
                                ->prefixIcon('heroicon-o-calendar')
                                ->placeholder('Seleccione el año de fabricación')
                                ->live(),

                            Forms\Components\Select::make('model_year')
                                ->label('Año de Modelo')
                                ->options(function() {
                                    $currentYear = (int)date('Y');
                                    $years = range($currentYear + 1, 1900);
                                    return array_combine($years, $years);
                                })
                                ->required()
                                ->searchable()
                                ->prefixIcon('heroicon-o-calendar-days')
                                ->placeholder('Seleccione el año del modelo')
                                ->live(),
                        ]),
                    ]),

                Wizard\Step::make('Especificaciones Técnicas')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->description('Características técnicas del vehículo')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('engine_cc')
                                ->label('Cilindrada (cc)')
                                ->numeric()
                                ->required()
                                ->minValue(100)
                                ->maxValue(10000)
                                ->step(1)
                                ->prefixIcon('heroicon-o-calculator'),

                            Forms\Components\Select::make('cylinders_id')
                                ->label('Cilindros')
                                ->relationship(
                                    name: 'cylinders',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'cilindros'))
                                )
                                ->prefixIcon('heroicon-o-adjustments-vertical')
                                ->required('El campo cilindros es obligatorio')
                                ->placeholder('Seleccione los cilindros')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('fuel_type_id')
                                ->label('Tipo de Combustible')
                                ->relationship(
                                    name: 'fuelType',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'combustible'))
                                )
                                ->prefixIcon('heroicon-o-fire')
                                ->required('El campo tipo de combustible es obligatorio')
                                ->placeholder('Seleccione el tipo de combustible')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\TextInput::make('mileage')
                                ->label('Kilometraje')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(999999)
                                ->step(1)
                                ->prefixIcon('heroicon-o-map'),

                            Forms\Components\Select::make('doors_id')
                                ->label('Número de Puertas')
                                ->relationship(
                                    name: 'doors',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'puertas'))
                                )
                                ->prefixIcon('heroicon-o-key')
                                ->required('El campo número de puertas es obligatorio')
                                ->placeholder('Seleccione el número de puertas')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('traction_id')
                                ->label('Tracción')
                                ->relationship(
                                    name: 'traction',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'traccion'))
                                )
                                ->prefixIcon('heroicon-o-bolt')
                                ->required('El campo tracción es obligatorio')
                                ->placeholder('Seleccione el tipo de tracción')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('color_id')
                                ->label('Color')
                                ->relationship(
                                    name: 'color',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'color'))
                                )
                                ->prefixIcon('heroicon-o-swatch')
                                ->required('El campo color es obligatorio')
                                ->placeholder('Seleccione un color')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('location_id')
                                ->label('Ubicación')
                                ->relationship(
                                    name: 'location',
                                    titleAttribute: 'value',
                                    modifyQueryUsing: fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'ubicacion'))
                                )
                                ->prefixIcon('heroicon-o-map-pin')
                                ->required('El campo ubicación es obligatorio')
                                ->placeholder('Seleccione una ubicación')
                                ->searchable()
                                ->preload()
                                ->live(),
                        ]),
                    ]),

                Wizard\Step::make('Descripción y Documentos')
                    ->icon('heroicon-o-document-text')
                    ->description('Información adicional y documentación')
                    ->schema([
                        Forms\Components\RichEditor::make('additional_description')
                            ->label('Descripción Adicional')
                            ->placeholder('Ingrese detalles adicionales sobre el vehículo...')
                            ->nullable()
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Group::make()->schema([
                                Forms\Components\FileUpload::make('soat_document')
                                    ->label('SOAT')
                                    ->disk('public')
                                    ->directory('vehicle-documents/soat')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->moveFiles()
                                    ->openable()
                                    ->downloadable()
                                    ->visibility('public')
                                    ->getUploadedFileNameForStorageUsing(function ($file) {
                                        return 'soat_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                    })
                                    ->helperText('Sube el SOAT en PDF o imagen (máx. 5MB)'),

                                Forms\Components\DatePicker::make('soat_expiry')
                                    ->label('Fecha de Vencimiento')
                                    ->hidden()
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d'),
                            ])->columnSpan(1),

                            Forms\Components\Group::make()->schema([
                                Forms\Components\FileUpload::make('revision_document')
                                    ->label('Revisión Técnica')
                                    ->disk('public')
                                    ->directory('vehicle-documents/revision')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->moveFiles()
                                    ->openable()
                                    ->downloadable()
                                    ->visibility('public')
                                    ->getUploadedFileNameForStorageUsing(function ($file) {
                                        return 'revision_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                    })
                                    ->helperText('Sube la Revisión Técnica en PDF o imagen (máx. 5MB)'),

                                Forms\Components\DatePicker::make('revision_expiry')
                                    ->label('Fecha de Vencimiento')
                                    ->hidden()
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d'),
                            ])->columnSpan(1),

                            Forms\Components\Group::make()->schema([
                                Forms\Components\FileUpload::make('tarjeta_document')
                                    ->label('Tarjeta de Propiedad')
                                    ->disk('public')
                                    ->directory('vehicle-documents/tarjeta')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->moveFiles()
                                    ->openable()
                                    ->downloadable()
                                    ->visibility('public')
                                    ->getUploadedFileNameForStorageUsing(function ($file) {
                                        return 'tarjeta_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                    })
                                    ->helperText('Sube la Tarjeta de Propiedad en PDF o imagen (máx. 5MB)'),

                                Forms\Components\DatePicker::make('tarjeta_expiry')
                                    ->label('Fecha de Vencimiento')
                                    ->hidden()
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d'),
                            ])->columnSpan(1),
                        ]),
                    ]),

                Wizard\Step::make('Equipamiento')
                    ->icon('heroicon-o-cog')
                    ->description('Características y equipamiento del vehículo')
                    ->schema([
                        Forms\Components\TextInput::make('airbags_count')
                            ->label('Cantidad de Airbags')
                            ->numeric()
                            ->placeholder('Ingrese cantidad')
                            ->prefixIcon('heroicon-o-shield-check'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('air_conditioning')
                                    ->label('Aire Acondicionado')
                                    ->inline(false),
                                Forms\Components\Toggle::make('alarm')
                                    ->label('Alarma')
                                    ->inline(false),
                                Forms\Components\Toggle::make('apple_carplay')
                                    ->label('Apple Car Play / Android Auto')
                                    ->inline(false),
                                Forms\Components\Toggle::make('wheels')
                                    ->label('Aros')
                                    ->inline(false),
                                Forms\Components\Toggle::make('alloy_wheels')
                                    ->label('Aros de Aleación')
                                    ->inline(false),
                                Forms\Components\Toggle::make('electric_seats')
                                    ->label('Asientos eléctricos')
                                    ->inline(false),
                                Forms\Components\Toggle::make('leather_seats')
                                    ->label('Asientos de cuero')
                                    ->inline(false),
                                Forms\Components\Toggle::make('front_camera')
                                    ->label('Cámara delantera')
                                    ->inline(false),
                                Forms\Components\Toggle::make('right_camera')
                                    ->label('Cámara lateral derecha')
                                    ->inline(false),
                                Forms\Components\Toggle::make('left_camera')
                                    ->label('Cámara lateral izquierda')
                                    ->inline(false),
                                Forms\Components\Toggle::make('rear_camera')
                                    ->label('Cámara trasera')
                                    ->inline(false),
                                Forms\Components\Toggle::make('mono_zone_ac')
                                    ->label('Climatizador Mono-zona')
                                    ->inline(false),
                                Forms\Components\Toggle::make('multi_zone_ac')
                                    ->label('Climatizador Multi-zona')
                                    ->inline(false),
                                Forms\Components\Toggle::make('bi_zone_ac')
                                    ->label('Climatizador Bi-zona')
                                    ->inline(false),
                                Forms\Components\Toggle::make('usb_ports')
                                    ->label('Conectores USB')
                                    ->inline(false),
                                Forms\Components\Toggle::make('steering_controls')
                                    ->label('Controles en el timón')
                                    ->inline(false),
                                Forms\Components\Toggle::make('front_fog_lights')
                                    ->label('Faros antiniebla delantero')
                                    ->inline(false),
                                Forms\Components\Toggle::make('rear_fog_lights')
                                    ->label('Faros antiniebla traseros')
                                    ->inline(false),
                                Forms\Components\Toggle::make('bi_led_lights')
                                    ->label('Luces Bi-Led')
                                    ->inline(false),
                                Forms\Components\Toggle::make('halogen_lights')
                                    ->label('Luces Halógenas')
                                    ->inline(false),
                                Forms\Components\Toggle::make('led_lights')
                                    ->label('Luces Led')
                                    ->inline(false),
                                Forms\Components\Toggle::make('abs_ebs')
                                    ->label('Frenos ABS/EBS')
                                    ->inline(false),
                                Forms\Components\Toggle::make('security_glass')
                                    ->label('Láminas de Seguridad')
                                    ->inline(false),
                                Forms\Components\Toggle::make('anti_collision')
                                    ->label('Sistema anti-colisión')
                                    ->inline(false),
                                Forms\Components\Toggle::make('gps')
                                    ->label('Localizador (GPS)')
                                    ->inline(false),
                                Forms\Components\Toggle::make('touch_screen')
                                    ->label('Pantalla Touch')
                                    ->inline(false),
                                Forms\Components\Toggle::make('speakers')
                                    ->label('Parlantes/Bajos')
                                    ->inline(false),
                                Forms\Components\Toggle::make('cd_player')
                                    ->label('Radio CD')
                                    ->inline(false),
                                Forms\Components\Toggle::make('mp3_player')
                                    ->label('Radio MP3')
                                    ->inline(false),
                                Forms\Components\Toggle::make('electric_mirrors')
                                    ->label('Retrovisores Eléctricos')
                                    ->inline(false),
                                Forms\Components\Toggle::make('parking_sensors')
                                    ->label('Sensores de parqueo')
                                    ->inline(false),
                                Forms\Components\Toggle::make('sunroof')
                                    ->label('Sunroof')
                                    ->inline(false),
                                Forms\Components\Toggle::make('cruise_control')
                                    ->label('Velocidad crucero')
                                    ->inline(false),
                                Forms\Components\Toggle::make('roof_rack')
                                    ->label('Parrilla Techo')
                                    ->inline(false),
                                Forms\Components\Toggle::make('factory_warranty')
                                    ->label('Garantía de fábrica')
                                    ->inline(false),
                                Forms\Components\Toggle::make('complete_documentation')
                                    ->label('Documentación Completa y Vigente')
                                    ->inline(false),
                                Forms\Components\Toggle::make('guaranteed_mileage')
                                    ->label('Historial y Kilometraje garantizado')
                                    ->inline(false),
                                Forms\Components\Toggle::make('part_payment')
                                    ->label('Opción Parte de Pago')
                                    ->inline(false),
                                Forms\Components\Toggle::make('financing')
                                    ->label('Posibilidad de Financiamiento')
                                    ->inline(false),
                            ]),
                    ]),
            ])
            ->persistStepInQueryString()
            ->columns([
                'sm' => 1,
                'lg' => 2,
                'xl' => 3,
            ])
            ->columnSpanFull()
            ->nextAction(
                fn (Action $action) => $action
                    ->label('Siguiente')
                    ->icon('heroicon-m-arrow-right')
                    ->color('primary')
                    ->size('lg')
            )
            ->previousAction(
                fn (Action $action) => $action
                    ->label('Anterior')
                    ->icon('heroicon-m-arrow-left')
                    ->outlined()
                    ->size('lg')
            )
            ->submitAction(new HtmlString(Blade::render(<<<BLADE
                <x-filament::button
                    type="submit"
                    size="lg"
                    color="success"
                >
                    <x-slot name="icon">
                        <x-heroicon-m-check class="w-5 h-5" />
                    </x-slot>
                    Guardar Vehículo
                </x-filament::button>
            BLADE))),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Stack::make([
                    Tables\Columns\TextColumn::make('plate')
                        ->formatStateUsing(function ($state, $record) {
                            return "
                                <div class='car-card'>
                                    <div class='plate'>
                                        <div class='plate-label' style='color: #4263eb;'>Placa</div>
                                        <div class='plate-number' style='font-size: 1.5rem; font-weight: 700; color: #1f2937;'>{$state}</div>
                                    </div>
                                    
                                    <div class='car-details' style='margin-top: 1.25rem;'>
                                        <div style='display: grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.5rem;'>
                                            <span style='color: #6b7280; font-weight: 500;'>Marca:</span>
                                            <span style='color: #111827; font-weight: 500;'>{$record->brand->value}</span>
                                        </div>
                                        
                                        <div style='display: grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.5rem;'>
                                            <span style='color: #6b7280; font-weight: 500;'>Modelo:</span>
                                            <span style='color: #111827; font-weight: 500;'>{$record->model->value}</span>
                                        </div>
                                        
                                        <div style='display: grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.5rem;'>
                                            <span style='color: #6b7280; font-weight: 500;'>Año:</span>
                                            <span style='color: #111827; font-weight: 500;'>{$record->year_made}</span>
                                        </div>
                                        
                                        <div style='display: grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.5rem;'>
                                            <span style='color: #6b7280; font-weight: 500;'>Km:</span>
                                            <span style='color: #111827; font-weight: 500;'>{$record->mileage}</span>
                                        </div>
                                        
                                        <div style='display: grid; grid-template-columns: 100px 1fr; gap: 0.75rem; margin-bottom: 0.5rem;'>
                                            <span style='color: #6b7280; font-weight: 500;'>Color:</span>
                                            <span style='color: #111827; font-weight: 500;'>{$record->color->value}</span>
                                        </div>
                                    </div>

                                    <div style='margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;'>
                                        <div style='display: flex; align-items: center; gap: 0.5rem; color: #4b5563;'>
                                            <span>📍</span>
                                            <span>{$record->location->value}</span>
                                        </div>
                                    </div>
                                </div>
                            ";
                        })
                        ->html()
                        ->searchable()
                        ->sortable(),
                ]),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/crear'),
            'edit'   => Pages\EditVehicle::route('/{record}/editar'),
        ];
    }
}

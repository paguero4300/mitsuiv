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

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Vehículo';
    protected static ?string $pluralLabel = 'Vehículos';
    protected static ?string $navigationGroup = 'Configuración Subastas';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            /* ---------------------------------------
             | SECCIÓN: Información General del Vehículo
             ----------------------------------------*/
            Forms\Components\Section::make('Información General del Vehículo')
                ->icon('heroicon-o-clipboard-document-list')
                ->description('Datos principales del vehículo')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('plate')
                            ->label('Placa')
                            ->placeholder('Ingrese la placa del vehículo')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->regex('/^[A-Z0-9-]+$/')
                            ->helperText('La placa debe contener solo letras mayúsculas, números y guiones')
                            ->prefixIcon('heroicon-o-identification'),

                        Forms\Components\Select::make('brand_id')
                            ->label('Marca')
                            ->relationship(
                                'brand',
                                'value',
                                fn($query) =>
                                $query->whereHas('type', fn($q) => $q->where('name', 'marca'))
                            )
                            ->required()
                            ->placeholder('Seleccione una marca')
                            ->prefixIcon('heroicon-o-building-storefront')
                            ->reactive(),

                        Forms\Components\Select::make('model_id')
                            ->label('Modelo')
                            ->relationship(
                                'model',
                                'value',
                                fn($query, $get) =>
                                $query->whereHas('type', fn($q) => $q->where('name', 'modelo'))
                                    ->where('parent_id', $get('brand_id'))
                            )
                            ->required()
                            ->placeholder('Seleccione un modelo')
                            ->prefixIcon('heroicon-o-tag')
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
                                'transmission',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'transmision'))
                            )
                            ->prefixIcon('heroicon-o-cog-6-tooth')
                            ->required(),

                        Forms\Components\Select::make('body_type_id')
                            ->label('Carrocería')
                            ->relationship(
                                'bodyType',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'carroceria'))
                            )
                            ->prefixIcon('heroicon-o-cube')
                            ->required(),

                        Forms\Components\TextInput::make('year_made')
                            ->label('Año de Fabricación')
                            ->numeric()
                            ->required()
                            ->minValue(1900)
                            ->maxValue(date('Y'))
                            ->prefixIcon('heroicon-o-calendar'),

                        Forms\Components\TextInput::make('model_year')
                            ->label('Año de Modelo')
                            ->numeric()
                            ->required()
                            ->minValue(1900)
                            ->maxValue(date('Y') + 1)
                            ->prefixIcon('heroicon-o-calendar-days'),
                    ]),
                ]),

            /* ----------------------------------------
             | SECCIÓN: Especificaciones Técnicas
             ----------------------------------------*/
            Forms\Components\Section::make('Especificaciones Técnicas')
                ->icon('heroicon-o-wrench-screwdriver')
                ->description('Características técnicas del vehículo')
                ->collapsible()
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
                                'cylinders',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'cilindros'))
                            )
                            ->prefixIcon('heroicon-o-adjustments-vertical')
                            ->required(),

                        Forms\Components\Select::make('fuel_type_id')
                            ->label('Tipo de Combustible')
                            ->relationship(
                                'fuelType',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'combustible'))
                            )
                            ->prefixIcon('heroicon-o-fire')
                            ->required(),

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
                                'doors',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'puertas'))
                            )
                            ->prefixIcon('heroicon-o-key')
                            ->required(),

                        Forms\Components\Select::make('traction_id')
                            ->label('Tracción')
                            ->relationship(
                                'traction',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'traccion'))
                            )
                            ->prefixIcon('heroicon-o-bolt')
                            ->required(),

                        Forms\Components\Select::make('color_id')
                            ->label('Color')
                            ->relationship(
                                'color',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'color'))
                            )
                            ->prefixIcon('heroicon-o-swatch')
                            ->required(),

                        Forms\Components\Select::make('location_id')
                            ->label('Ubicación')
                            ->relationship(
                                'location',
                                'value',
                                fn($query) => $query->whereHas('type', fn($q) => $q->where('name', 'ubicacion'))
                            )
                            ->prefixIcon('heroicon-o-map-pin')
                            ->required(),
                    ]),
                ]),

            /* ----------------------------------------
             | SECCIÓN: Documentos del Vehículo
             ----------------------------------------*/
            Forms\Components\Section::make('Documentos del Vehículo')
                ->icon('heroicon-o-document-duplicate')
                ->description('Adjunte los documentos del vehículo')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        /* ---------------- SOAT ---------------- */
                        Forms\Components\Group::make()->schema([
                            Forms\Components\FileUpload::make('soat_document')
                                ->label('SOAT')
                                ->disk('public')
                                ->directory('vehicle-documents/soat')
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(5120) // 5 MB
                                ->moveFiles()    // Mueve el archivo al enviar
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

                        /* ---------- Revisión Técnica ---------- */
                        Forms\Components\Group::make()->schema([
                            Forms\Components\FileUpload::make('revision_document')
                                ->label('Revisión Técnica')
                                ->helperText('Formato PDF o imágenes (máx. 5MB)')
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

                        /* ---- Tarjeta de Propiedad ---- */
                        Forms\Components\Group::make()->schema([
                            Forms\Components\FileUpload::make('tarjeta_document')
                                ->label('Tarjeta de Propiedad')
                                ->helperText('Formato PDF o imágenes (máx. 5MB)')
                                ->disk('public')
                                ->directory('vehicle-documents/tarjeta')
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(5120)
                                ->moveFiles()
                                ->openable()
                                ->downloadable()
                                ->visibility('public')
                                ->getUploadedFileNameForStorageUsing(function ($file) {
                                    return '' . uniqid() . '.' . $file->getClientOriginalExtension();
                                })
                                ->helperText('Sube la Tarjeta de Propiedad en PDF o imagen (máx. 5MB)'),

                            Forms\Components\DatePicker::make('tarjeta_expiry')
                                ->label('Fecha de Vencimiento')
                                ->displayFormat('d/m/Y')
                                ->hidden()
                                ->format('Y-m-d'),
                        ])->columnSpan(1),
                    ]),
                ]),

            /* ----------------------------------------
             | SECCIÓN: Descripción
             ----------------------------------------*/
            Forms\Components\Section::make('Descripción')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    Forms\Components\RichEditor::make('additional_description')
                        ->label('Descripción Adicional')
                        ->placeholder('Ingrese detalles adicionales sobre el vehículo...')
                        ->nullable()
                        ->maxLength(65535) // Límite para campo TEXT
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Equipamiento')
                ->icon('heroicon-o-cog')
                ->description('Características y equipamiento del vehículo')
                ->collapsible()
                ->relationship('equipment')
                ->schema([
                    // Campo para la cantidad de Airbags
                    Forms\Components\TextInput::make('airbags_count')
                        ->label('Cantidad de Airbags')
                        ->numeric()
                        ->placeholder('Ingrese cantidad')
                        ->prefixIcon('heroicon-o-shield-check'),

                    // Grid con todos los toggles que coinciden con la base de datos
                    Forms\Components\Grid::make(3)
                        ->schema([
                            // Características básicas
                            Forms\Components\Toggle::make('air_conditioning')
                                ->label('Aire Acondicionado')
                                ->inline(false),
                            Forms\Components\Toggle::make('alarm')
                                ->label('Alarma')
                                ->inline(false),
                            Forms\Components\Toggle::make('apple_carplay')
                                ->label('Apple Car Play / Android Auto')
                                ->inline(false),

                            // Ruedas
                            Forms\Components\Toggle::make('wheels')
                                ->label('Aros')
                                ->inline(false),
                            Forms\Components\Toggle::make('alloy_wheels')
                                ->label('Aros de Aleación')
                                ->inline(false),

                            // Asientos
                            Forms\Components\Toggle::make('electric_seats')
                                ->label('Asientos eléctricos')
                                ->inline(false),
                            Forms\Components\Toggle::make('leather_seats')
                                ->label('Asientos de cuero')
                                ->inline(false),

                            // Cámaras
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

                            // Climatización
                            Forms\Components\Toggle::make('mono_zone_ac')
                                ->label('Climatizador Mono-zona')
                                ->inline(false),
                            Forms\Components\Toggle::make('multi_zone_ac')
                                ->label('Climatizador Multi-zona')
                                ->inline(false),
                            Forms\Components\Toggle::make('bi_zone_ac')
                                ->label('Climatizador Bi-zona')
                                ->inline(false),

                            // Conectividad y controles
                            Forms\Components\Toggle::make('usb_ports')
                                ->label('Conectores USB')
                                ->inline(false),
                            Forms\Components\Toggle::make('steering_controls')
                                ->label('Controles en el timón')
                                ->inline(false),

                            // Iluminación
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

                            // Seguridad y asistencia
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

                            // Multimedia
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

                            // Características adicionales
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

                            // Documentación y garantías
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plate')
                    ->label('Placa')
                    ->searchable(),

                Tables\Columns\TextColumn::make('brand.value')
                    ->label('Marca')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('model.value')
                    ->label('Modelo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versión')
                    ->searchable(),

                Tables\Columns\TextColumn::make('year_made')
                    ->label('Año de Fabricación')
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_year')
                    ->label('Año de Modelo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mileage')
                    ->label('Kilometraje')
                    ->sortable(),

                Tables\Columns\TextColumn::make('color.value')
                    ->label('Color')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('location.value')
                    ->label('Ubicación')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ImagesRelationManager::class, // Añadir el Relation Manager de Imágenes
            // Puedes añadir otros Relation Managers si los tienes
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

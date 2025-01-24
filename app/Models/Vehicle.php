<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;

class Vehicle extends Model implements HasMedia
{
    protected $fillable = [
        'plate',
        'brand_id',
        'model_id',
        'version',
        'transmission_id',
        'body_type_id',
        'year_made',
        'model_year',
        'engine_cc',
        'cylinders_id',
        'fuel_type_id',
        'mileage',
        'doors_id',
        'traction_id',
        'color_id',
        'location_id',
        'additional_description',
        'soat_expiry',
        'revision_expiry',
        'tarjeta_expiry',
    ];

    protected $casts = [
        'year_made' => 'integer',
        'model_year' => 'integer',
        'engine_cc' => 'integer',
        'mileage' => 'integer',
        'soat_expiry' => 'date',
        'revision_expiry' => 'date',
        'tarjeta_expiry' => 'date',
    ];

    use InteractsWithMedia;

   

    

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogValue::class, 'brand_id')
            ->select(['id', 'value', 'catalog_type_id'])
            ->where('catalog_type_id', 1)
            ->where('active', true);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(CatalogValue::class, 'model_id')
            ->select(['id', 'value', 'catalog_type_id'])
            ->where('catalog_type_id', 2);
    }

   

    public function transmission()
    {
        return $this->belongsTo(CatalogValue::class, 'transmission_id')->select('id', 'value');
    }

    public function bodyType()
    {
        return $this->belongsTo(CatalogValue::class, 'body_type_id')->select('id', 'value');
    }

    public function cylinders()
    {
        return $this->belongsTo(CatalogValue::class, 'cylinders_id')->select('id', 'value');
    }

    public function fuelType()
    {
        return $this->belongsTo(CatalogValue::class, 'fuel_type_id')->select('id', 'value');
    }

    public function doors()
    {
        return $this->belongsTo(CatalogValue::class, 'doors_id')->select('id', 'value');
    }

    public function traction()
    {
        return $this->belongsTo(CatalogValue::class, 'traction_id')->select('id', 'value');
    }

    public function color()
    {
        return $this->belongsTo(CatalogValue::class, 'color_id')->select('id', 'value');
    }

    public function location()
    {
        return $this->belongsTo(CatalogValue::class, 'location_id')->select('id', 'value');
    }


    public function equipment(): HasOne
    {
        return $this->hasOne(VehicleEquipment::class);
    }

    public function images()
    {
        return $this->hasMany(VehicleImage::class);
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class)
            ->select(['id', 'vehicle_id', 'type', 'path', 'expiry_date'])
            ->whereIn('type', ['soat', 'tarjeta_propiedad', 'revision_tecnica']);
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class);
    }

    public function getDocumentByType(string $type): ?VehicleDocument
    {
        return $this->documents()->where('type', $type)->first();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle-images')
            ->useDisk('public');
            
        $this->addMediaCollection('soat_document')
            ->singleFile()
            ->useDisk('public');
            
        $this->addMediaCollection('revision_document')
            ->singleFile()
            ->useDisk('public');
            
        $this->addMediaCollection('tarjeta_document')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200);

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600);
    }

    public function soat_document(): HasOne
    {
        return $this->hasOne(VehicleDocument::class)
            ->select(['id', 'vehicle_id', 'type', 'path', 'expiry_date'])
            ->where('type', 'soat')
            ->latest('id')
            ->limit(1)
            ->withDefault();
    }

    public function tarjeta_document(): HasOne
    {
        return $this->hasOne(VehicleDocument::class)
            ->select(['id', 'vehicle_id', 'type', 'path', 'expiry_date'])
            ->where('type', 'tarjeta_propiedad')
            ->latest('id')
            ->limit(1)
            ->withDefault();
    }

    public function revision_document():HasOne
    {
        return $this->hasOne(VehicleDocument::class)
            ->select(['id', 'vehicle_id', 'type', 'path', 'expiry_date'])
            ->where('type', 'revision_tecnica')
            ->latest('id')
            ->limit(1)
            ->withDefault();
    }

    public function getGroupedDocuments()
    {
        return $this->documents()
            ->get()
            ->groupBy('type');
    }

    public function loadCatalogValues()
    {
        DB::enableQueryLog();

        // Cargamos todo en una sola consulta
        $vehicle = self::with([
            'brand' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'model' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'transmission' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'bodyType' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'cylinders' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'fuelType' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'doors' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'traction' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'color' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true),
            'location' => fn($q) => $q->select(['id', 'value', 'catalog_type_id'])->where('active', true)
        ])
        ->select([
            'id', 'plate', 'brand_id', 'model_id', 'version',
            'transmission_id', 'body_type_id', 'cylinders_id',
            'fuel_type_id', 'doors_id', 'traction_id', 'color_id',
            'location_id'
        ])
        ->find($this->id);

        $queries = DB::getQueryLog();
        
        Log::info('LoadCatalogValues Queries:', [
            'vehicle_id' => $this->id,
            'queries' => $queries,
            'relations' => [
                'transmission' => $vehicle->transmission?->toArray(),
                'brand' => $vehicle->brand?->toArray(),
                'model' => $vehicle->model?->toArray()
            ]
        ]);
        
        DB::disableQueryLog();
        
        return [
            'transmission' => $vehicle->transmission?->value ?? 'No especificado',
            'brand' => $vehicle->brand?->value ?? 'No especificado',
            'model' => $vehicle->model?->value ?? 'No especificado',
            'bodyType' => $vehicle->bodyType?->value ?? 'No especificado',
            'cylinders' => $vehicle->cylinders?->value ?? 'No especificado',
            'fuelType' => $vehicle->fuelType?->value ?? 'No especificado',
            'doors' => $vehicle->doors?->value ?? 'No especificado',
            'traction' => $vehicle->traction?->value ?? 'No especificado',
            'color' => $vehicle->color?->value ?? 'No especificado',
            'location' => $vehicle->location?->value ?? 'No especificado',
            '_debug_queries' => $queries
        ];
    }

    public function debugRelations()
    {
        DB::enableQueryLog();

        // Cargamos solo transmisión para depurar
        $this->load(['transmission']);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Hacemos una consulta directa para verificar
        $transmission = DB::table('catalog_values')
            ->where('id', $this->transmission_id)
            ->where('catalog_type_id', 9)
            ->first();

        return [
            'vehicle_id' => $this->id,
            'transmission_id' => $this->transmission_id,
            'transmission_relation' => $this->transmission?->toArray(),
            'direct_query' => $transmission,
            'all_queries' => $queries,
            'raw_data' => [
                'catalog_value' => DB::table('catalog_values')->where('id', 65)->first(),
                'catalog_type' => DB::table('catalog_types')->where('id', 9)->first(),
            ]
        ];
    }

}

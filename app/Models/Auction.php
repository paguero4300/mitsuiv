<?php

namespace App\Models;

use App\Traits\HasBidStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    use HasBidStatus;

    protected $fillable = [
        'vehicle_id',
        'appraiser_id',
        'status_id',
        'start_date',
        'end_date',
        'duration_hours',
        'base_price',
        'current_price'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)
            ->select([
                'id', 
                'plate', 
                'brand_id', 
                'model_id', 
                'version',
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
                'additional_description'
            ]);
    }
    
    public function status()
    {
        return $this->belongsTo(AuctionStatus::class, 'status_id');
    }

    public function appraiser()
    {
        return $this->belongsTo(User::class, 'appraiser_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class)->orderBy('created_at', 'desc');
    }

    public function adjudication()
    {
        return $this->hasOne(AuctionAdjudication::class);
    }

    public function canBid(): bool
    {
        return $this->end_date > now() && 
               in_array($this->status_id, [2, 3]); // En Proceso o Sin Oferta
    }

    public function auctionSetting()
    {
        return $this->belongsTo(AuctionSetting::class, 'setting_id');
    }

    public function getMinimumBidIncrement(): float
    {
        $currentPrice = $this->current_price ?? $this->base_price;
        
        // Configuración por defecto si no hay setting
        $defaultIncrement = 100;
        
        // Obtener todos los settings
        $allSettings = AuctionSetting::all();
        $allRanges = collect();
        
        // Combinar todos los rangos de los settings
        foreach ($allSettings as $setting) {
            $ranges = collect($setting->value);
            $allRanges = $allRanges->concat($ranges);
        }
        
        // Ordenar por min_value para procesar en orden
        $allRanges = $allRanges->sortBy('min_value');
        
        foreach ($allRanges as $range) {
            $minValue = (float) ($range['min_value'] ?? 0);
            $maxValue = $range['max_value'] ? (float) $range['max_value'] : PHP_FLOAT_MAX;
            
            if ($currentPrice >= $minValue && $currentPrice < $maxValue) {
                return (float) ($range['increment'] ?? $defaultIncrement);
            }
        }
        
        return $defaultIncrement;
    }

    public function getBidStatusAttribute(): string
    {
        // Si la subasta ya terminó
        if ($this->end_date < now()) {
            // Verificar si hubo un ganador
            $winningBid = $this->bids()->orderByDesc('amount')->first();
            
            if (!$winningBid) {
                return 'Subasta Fallida';
            }
            
            // Si el usuario actual es el ganador
            if ($winningBid->reseller_id === auth()->id()) {
                return 'Subasta Ganada';
            }
            
            return 'Subasta Perdida';
        }
        
        // Obtener la puja más alta del usuario actual
        $userBid = $this->bids()
            ->where('reseller_id', auth()->id())
            ->orderByDesc('amount')
            ->first();
        
        // Si el usuario no ha hecho ninguna puja
        if (!$userBid) {
            return 'Sin Oferta';
        }
        
        // Verificar si hay una puja más alta de otro usuario
        $higherBid = $this->bids()
            ->where('reseller_id', '!=', auth()->id())
            ->where('amount', '>', $userBid->amount)
            ->exists();
        
        return $higherBid ? 'Puja Superada' : 'Puja Líder';
    }

    public function getBidStatusColorAttribute(): string
    {
        return match($this->bid_status) {
            'Puja Líder' => 'success',
            'Puja Superada' => 'warning',
            'Subasta Ganada' => 'success',
            'Subasta Perdida' => 'danger',
            'Subasta Fallida' => 'danger',
            'Sin Oferta' => 'gray',
            default => 'gray'
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($auction) {
            if (empty($auction->status_id)) {
                $auction->status_id = \App\Models\AuctionStatus::where('slug', 'sin-oferta')->first()->id;
            }
        });
    }
}

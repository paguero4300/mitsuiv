<?php

namespace App\Models;

use App\Traits\HasBidStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Auction extends Model implements Auditable
{
    use HasBidStatus, AuditableTrait;

    // Determinar qué campos serán auditados (opcional)
    protected $auditInclude = [
        'vehicle_id',
        'appraiser_id',
        'status_id',
        'start_date',
        'end_date',
        'base_price',
        'current_price'
    ];

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

    protected const TIMEZONE = 'America/Lima';

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)
            ->select([
                'id', 
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

    public function adjudications()
    {
        return $this->hasMany(AuctionAdjudication::class, 'auction_id');
    }

    /**
     * Verifica si se pueden realizar pujas en la subasta
     * 
     * Estados y transiciones permitidas:
     * - Sin Oferta (2): Estado inicial, puede recibir pujas
     * - En Proceso (3): Ya tiene pujas, puede recibir más
     * 
     * Estados finales (no permiten pujas):
     * - Fallida (4): Cerrada sin ofertas o rechazada
     * - Ganada (5): Cerrada con ofertas, pendiente de adjudicación
     * - Adjudicada (6): Venta concretada
     */
    public function canBid(): bool
    {
        // 1. Validar tiempo
        $now = now()->timezone(self::TIMEZONE);
        $timeValid = $this->start_date <= $now && $this->end_date > $now;
        
        if (!$timeValid) {
            return false;
        }

        // 2. Validar estado
        $allowedStatuses = [
            \App\Models\AuctionStatus::SIN_OFERTA,
            \App\Models\AuctionStatus::EN_PROCESO
        ];

        // Estados finales que no permiten pujas
        $finalStatuses = [
            \App\Models\AuctionStatus::FALLIDA,
            \App\Models\AuctionStatus::GANADA,
            \App\Models\AuctionStatus::ADJUDICADA
        ];

        // Si está en un estado final, no permite pujas
        if (in_array($this->status_id, $finalStatuses)) {
            return false;
        }

        // Solo permite pujas en estados válidos
        return in_array($this->status_id, $allowedStatuses);
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
        $userBid = $this->getUserBid();
        
        if (!$userBid) {
            return 'Sin Oferta';
        }

        // Si la subasta está adjudicada
        if ($this->status_id === \App\Models\AuctionStatus::ADJUDICADA) {
            $winningBid = $this->bids()->orderByDesc('amount')->first();
            if ($winningBid && $winningBid->reseller_id === Auth::id()) {
                return 'Subasta Adjudicada';
            }
            return 'Subasta Perdida';
        }

        // Si la subasta está fallida
        if ($this->status_id === \App\Models\AuctionStatus::FALLIDA) {
            return 'Subasta Fallida';
        }

        // Si la subasta está ganada
        if ($this->status_id === \App\Models\AuctionStatus::GANADA) {
            $winningBid = $this->bids()->orderByDesc('amount')->first();
            if ($winningBid && $winningBid->reseller_id === Auth::id()) {
                return 'Subasta Ganada';
            }
            return 'Subasta Perdida';
        }

        // Para subastas en proceso o sin oferta
        $leadingBid = $this->getLeadingBid();
        
        // Si no hay puja líder (no debería ocurrir, pero por seguridad)
        if (!$leadingBid) {
            return 'Sin Oferta';
        }

        if ($userBid->id === $leadingBid->id) {
            return 'Puja Líder';
        }

        return 'Puja Superada';
    }

    public function getBidStatusColorAttribute(): string
    {
        return match($this->bid_status) {
            'Puja Líder' => 'success',
            'Puja Superada' => 'warning',
            'Subasta Ganada' => 'success',
            'Subasta Perdida' => 'danger',
            'Subasta Fallida' => 'danger',
            'Subasta Adjudicada' => 'success',
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

            // Asegurar que las fechas estén en zona horaria de Lima
            if ($auction->start_date) {
                $auction->start_date = $auction->start_date->timezone(self::TIMEZONE);
            }
            if ($auction->end_date) {
                $auction->end_date = $auction->end_date->timezone(self::TIMEZONE);
            }
        });

        static::created(function ($auction) {
            \Illuminate\Support\Facades\Log::info('Auction Model: Evento created disparado', [
                'auction_id' => $auction->id,
                'class' => get_class($auction),
                'start_date' => $auction->start_date->format('Y-m-d H:i:s'),
                'end_date' => $auction->end_date->format('Y-m-d H:i:s'),
                'timezone' => self::TIMEZONE
            ]);
        });
    }

    // Asegurar que las fechas se devuelvan en zona horaria de Lima
    protected function serializeDate(\DateTimeInterface $date)
    {
        return \Carbon\Carbon::parse($date)->setTimezone(self::TIMEZONE);
    }

    // Asegurar que las fechas se guarden en zona horaria de Lima
    public function setStartDateAttribute($value)
    {
        $this->attributes['start_date'] = $value ? Carbon::parse($value)->timezone(self::TIMEZONE) : null;
    }

    public function setEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $value ? Carbon::parse($value)->timezone(self::TIMEZONE) : null;
    }
}

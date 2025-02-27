<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Bid extends Model implements Auditable
{
    use AuditableTrait;
    
    // Determinar qué campos serán auditados (opcional)
    protected $auditInclude = [
        'auction_id',
        'reseller_id',
        'amount',
        'comments',
    ];
    
    protected $fillable = [
        'auction_id',
        'reseller_id',
        'amount',
        'comments',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function isLeading(): bool
    {
        return $this->auction->current_price == $this->amount;
    }

    protected static function booted(): void
    {
        static::created(function (Bid $bid) {
            $bid->auction->update([
                'current_price' => $bid->amount,
                'status_id' => 3 // En Proceso
            ]);
        });
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionStatus extends Model
{
    protected $fillable = [
        'name',        // sin_oferta, en_proceso, etc
        'description', // Descripción del estado
        'background_color', // Color de fondo para UI
        'text_color',      // Color de texto para UI
        'display_order',   // Orden de visualización
        'active',         // Estado activo/inactivo
        'slug'           // Slug para URLs
    ];

    public const SIN_OFERTA = 2;
    public const EN_PROCESO = 3;
    public const FALLIDA = 4;
    public const GANADA = 5;
    public const ADJUDICADA = 6;
    public const PROPUESTA_LIDER = 7;
    public const PROPUESTA_SUPERADA = 8;
    public const PERDIDA = 9;

    public function auctions()
    {
        return $this->hasMany(Auction::class, 'status_id');
    }
}

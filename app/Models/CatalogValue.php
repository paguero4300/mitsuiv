<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CatalogValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_type_id',
        'parent_id',
        'value',
        'description',
        'active',
    ];

    /**
     * Relación con el valor padre (Marca para un Modelo).
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Relación con los valores hijos (Modelos de una Marca).
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Relación con el tipo de catálogo.
     */
    public function type()
    {
        return $this->belongsTo(CatalogType::class, 'catalog_type_id');
    }


  
}

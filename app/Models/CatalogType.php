<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active'
    ];


    public function values()
    {
        return $this->hasMany(CatalogValue::class);
    }
}

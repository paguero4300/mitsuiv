<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    protected $fillable = ['vehicle_id', 'type', 'path', 'expiry_date'];
    protected $casts = ['expiry_date' => 'date'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}

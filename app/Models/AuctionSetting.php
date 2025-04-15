<?php
// app/Models/AuctionSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AuctionSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description'
    ];

    protected $casts = [
        'value' => 'json'
    ];

    public function auctions()
    {
        return $this->hasMany(Auction::class, 'setting_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($auctionSetting) {
            if (empty($auctionSetting->key)) {
                $lastSetting = self::where('key', 'like', 'default_key_%')
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = $lastSetting
                    ? ((int) str_replace('default_key_', '', $lastSetting->key)) + 1
                    : 1;

                $auctionSetting->key = 'default_key_' . $nextNumber;
            }
            self::validateValueFormat($auctionSetting->value);
        });

        static::updating(function ($auctionSetting) {
            self::validateValueFormat($auctionSetting->value);
        });
    }

    public static function validateValueFormat($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            throw ValidationException::withMessages([
                'value' => 'El campo value debe ser un array válido.'
            ]);
        }

        foreach ($value as $item) {
            if (
                !isset($item['range_name']) ||
                !isset($item['min_value']) ||
                !isset($item['max_value']) ||
                !isset($item['increment'])
            ) {
                throw ValidationException::withMessages([
                    'value' => 'Cada elemento debe tener las claves: range_name, min_value, max_value e increment.'
                ]);
            }

            if (!is_numeric($item['min_value']) || !is_numeric($item['max_value']) || !is_numeric($item['increment'])) {
                throw ValidationException::withMessages([
                    'value' => 'Los valores de min_value, max_value e increment deben ser numéricos.'
                ]);
            }
        }
    }
}

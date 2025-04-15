<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionAdjudication extends Model
{
    protected $fillable = ['auction_id', 'reseller_id', 'status', 'notes'];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}

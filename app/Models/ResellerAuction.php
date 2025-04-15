<?php

namespace App\Models;
use App\Traits\HasBidStatus;
use Illuminate\Database\Eloquent\Model;

class ResellerAuction extends Auction
{
    protected $table = 'auctions';

    public function bids()
    {
        return $this->hasMany(Bid::class, 'auction_id');
    }

}

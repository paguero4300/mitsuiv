<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BidService
{
    public function placeBid(Auction $auction, User $user, float $amount): void
    {
        if (!$auction->canBid()) {
            throw new \Exception('La subasta no está disponible para pujas');
        }

        $currentPrice = $auction->current_price ?? $auction->base_price;
        $minimumBid = $currentPrice + $auction->getMinimumBidIncrement();

        if ($amount < $minimumBid) {
            throw new \Exception(
                sprintf(
                    'La puja debe ser al menos USD %s',
                    number_format($minimumBid, 2)
                )
            );
        }

        DB::transaction(function () use ($auction, $user, $amount) {
            $auction->bids()->create([
                'reseller_id' => $user->id,
                'amount' => $amount,
            ]);
            
            if ($amount > ($auction->current_price ?? $auction->base_price)) {
                $auction->update([
                    'current_price' => $amount,
                    'status_id' => 3, // En Proceso
                ]);
            }
        });
    }
}
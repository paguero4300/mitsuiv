<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BidService
{
    protected $auctionExtensionService;

    public function __construct(AuctionExtensionService $auctionExtensionService)
    {
        $this->auctionExtensionService = $auctionExtensionService;
    }

    public function placeBid(Auction $auction, User $user, float $amount, ?string $comments = null): void
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

        DB::transaction(function () use ($auction, $user, $amount, $comments) {
            // Crear la puja
            $bid = $auction->bids()->create([
                'reseller_id' => $user->id,
                'amount' => $amount,
                'comments' => $comments,
            ]);

            // Actualizar el precio actual y estado de la subasta
            if ($amount > ($auction->current_price ?? $auction->base_price)) {
                $auction->update([
                    'current_price' => $amount,
                    'status_id' => 3, // En Proceso
                ]);
            }

            // Verificar si la subasta necesita extensión de tiempo
            $wasExtended = $this->auctionExtensionService->extendAuctionIfNeeded($auction);

            if ($wasExtended) {
                Log::info('Tiempo de subasta extendido por puja en último minuto', [
                    'auction_id' => $auction->id,
                    'bid_id' => $bid->id,
                    'reseller_id' => $user->id,
                    'amount' => $amount,
                    'new_end_date' => $auction->end_date->format('Y-m-d H:i:s')
                ]);
            }
        });
    }
}
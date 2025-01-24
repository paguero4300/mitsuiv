<?php

namespace App\Traits;

use App\Models\Bid;
use Illuminate\Support\Facades\Auth;

trait HasBidStatus
{
    public function getLeadingBid(): ?Bid
    {
        return $this->bids()
            ->orderByDesc('amount')
            ->first();
    }

    public function getUserBid(): ?Bid
    {
        return $this->bids()
            ->where('reseller_id', Auth::id())
            ->orderByDesc('amount')
            ->first();
    }

    public function getBidStatusAttribute(): string
    {
        $userBid = $this->getUserBid();
        
        if (!$userBid) {
            return 'Sin Puja';
        }

        $leadingBid = $this->getLeadingBid();
        
        if ($userBid->id === $leadingBid->id) {
            return 'Puja Líder';
        }

        return 'Puja Superada';
    }

    public function getBidStatusColorAttribute(): string
    {
        return match($this->bid_status) {
            'Puja Líder' => 'success',
            'Puja Superada' => 'danger',
            default => 'gray'
        };
    }
} 
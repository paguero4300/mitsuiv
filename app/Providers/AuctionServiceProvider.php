<?php

namespace App\Providers;

use App\Services\AuctionExtensionService;
use App\Services\BidService;
use Illuminate\Support\ServiceProvider;

class AuctionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AuctionExtensionService::class, function ($app) {
            return new AuctionExtensionService();
        });

        $this->app->singleton(BidService::class, function ($app) {
            return new BidService($app->make(AuctionExtensionService::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

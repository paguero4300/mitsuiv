<?php

namespace App\Providers;

use App\Models\Auction;
use App\Observers\AuctionObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::enableQueryLog();
        Log::info('AppServiceProvider: Registrando observers...');
        Auction::observe(AuctionObserver::class);
        Log::info('AppServiceProvider: Observer de Auction registrado');
    }
}

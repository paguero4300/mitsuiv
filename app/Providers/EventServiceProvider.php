<?php

namespace App\Providers;

use App\Models\Auction;
use App\Observers\AuctionObserver;
use App\Models\Bid;
use App\Observers\BidObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Log::info('EventServiceProvider: Registrando observers...');
        Auction::observe(AuctionObserver::class);
        Log::info('EventServiceProvider: Observer de Auction registrado');
        Bid::observe(BidObserver::class);
        Log::info('EventServiceProvider: Observer de Bid registrado');
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
} 
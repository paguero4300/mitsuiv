<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;



Schedule::command('auctions:update-statuses')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('auctions:check-pending')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('auctions:check-ending')
    ->everyMinute()
    ->withoutOverlapping();
<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaWaController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/api/send-whatsapp', [NotificationController::class, 'sendWhatsapp'])
    ->name('whatsapp.send');

    Route::post('/api/send-meta-wa', [MetaWaController::class, 'sendMessage'])
    ->name('send.meta.wa');
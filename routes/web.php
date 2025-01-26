<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/send-whatsapp', [NotificationController::class, 'sendWhatsapp'])
    ->name('whatsapp.send');

    Route::post('/send-meta-wa', [MetaWaController::class, 'sendMessage'])
    ->name('send.meta.wa');
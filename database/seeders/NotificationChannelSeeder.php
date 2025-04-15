<?php

namespace Database\Seeders;

use App\Models\NotificationChannel;
use Illuminate\Database\Seeder;

class NotificationChannelSeeder extends Seeder
{
    public function run(): void
    {
        NotificationChannel::create([
            'channel_type' => 'email',
            'is_enabled' => true
        ]);

        NotificationChannel::create([
            'channel_type' => 'whatsapp',
            'is_enabled' => true
        ]);
    }
}
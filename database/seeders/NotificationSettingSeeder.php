<?php

namespace Database\Seeders;

use App\Models\NotificationChannel;
use App\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class NotificationSettingSeeder extends Seeder
{
    public function run(): void
    {
        $channels = NotificationChannel::all();
        
        foreach ($channels as $channel) {
            // Crear configuraciones para revendedores
            foreach (NotificationSetting::EVENT_TYPES['revendedor'] as $eventType => $description) {
                NotificationSetting::create([
                    'role_type' => 'revendedor',
                    'event_type' => $eventType,
                    'channel_id' => $channel->id,
                    'is_enabled' => true
                ]);
            }

            // Crear configuraciones para tasadores
            foreach (NotificationSetting::EVENT_TYPES['tasador'] as $eventType => $description) {
                NotificationSetting::create([
                    'role_type' => 'tasador',
                    'event_type' => $eventType,
                    'channel_id' => $channel->id,
                    'is_enabled' => true
                ]);
            }
        }
    }
}
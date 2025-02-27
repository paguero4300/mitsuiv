<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('role_type', ['revendedor', 'tasador']);
            $table->string('event_type', 100);
            $table->foreignId('channel_id')->constrained('notification_channels');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
            
            $table->unique(['role_type', 'event_type', 'channel_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_settings');
    }
};
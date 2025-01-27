<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->enum('channel_type', ['email', 'whatsapp']);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            
            $table->unique('channel_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_channels');
    }
};
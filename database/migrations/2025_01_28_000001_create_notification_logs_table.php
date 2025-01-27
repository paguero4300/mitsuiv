<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('channel_type');
            $table->foreignId('user_id')->constrained('users');
            $table->string('reference_id')->comment('ID de referencia (ej: ID de subasta)');
            $table->json('data')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['event_type', 'channel_type', 'user_id', 'reference_id'], 'unique_notification');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_logs');
    }
}; 
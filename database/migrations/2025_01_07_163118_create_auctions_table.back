<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('appraiser_id')->constrained('users');
            $table->foreignId('status_id')->constrained('auction_statuses');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('duration_hours');
            $table->decimal('base_price', 10, 2);
            $table->decimal('current_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};

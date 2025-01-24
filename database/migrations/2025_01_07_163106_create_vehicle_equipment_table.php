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
        Schema::create('vehicle_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->integer('airbags_count')->nullable();
            $table->boolean('air_conditioning')->default(false);
            $table->boolean('alarm')->default(false);
            $table->boolean('apple_carplay')->default(false);
            $table->boolean('wheels')->default(false);
            $table->boolean('alloy_wheels')->default(false);
            $table->boolean('electric_seats')->default(false);
            $table->boolean('leather_seats')->default(false);
            // Añadir todos los equipamientos mencionados en el documento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_equipment');
    }
};

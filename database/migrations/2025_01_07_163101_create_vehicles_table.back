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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique();
            $table->foreignId('brand_id')->constrained('catalog_values');
            $table->foreignId('model_id')->constrained('catalog_values');
            $table->string('version');
            $table->foreignId('transmission_id')->constrained('catalog_values');
            $table->foreignId('body_type_id')->constrained('catalog_values');
            $table->year('year_made');
            $table->year('model_year');
            $table->integer('engine_cc');
            $table->foreignId('cylinders_id')->constrained('catalog_values');
            $table->foreignId('fuel_type_id')->constrained('catalog_values');
            $table->integer('mileage');
            $table->foreignId('doors_id')->constrained('catalog_values');
            $table->foreignId('traction_id')->constrained('catalog_values');
            $table->foreignId('color_id')->constrained('catalog_values');
            $table->foreignId('location_id')->constrained('catalog_values');
            $table->text('additional_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

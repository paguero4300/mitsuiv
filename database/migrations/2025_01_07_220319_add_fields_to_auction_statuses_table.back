<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_statuses', function (Blueprint $table) {
            $table->string('name')->after('id');              // sin_oferta, en_proceso, etc
            $table->text('description')->after('name');       // descripción del estado
            $table->string('background_color')->after('description')->default('#FFFFFF');
            $table->string('text_color')->after('background_color')->default('#000000');
            $table->integer('display_order')->after('text_color')->default(0);
            $table->boolean('active')->after('display_order')->default(true);
            $table->string('slug')->after('active')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('auction_statuses', function (Blueprint $table) {
            $table->dropColumn(['name', 'description', 'background_color', 'text_color', 'display_order', 'active', 'slug']);
        });
    }
};

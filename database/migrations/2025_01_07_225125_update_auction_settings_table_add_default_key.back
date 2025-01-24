<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::table('auction_settings', function (Blueprint $table) {
            $table->string('key')->default('default_key')->change();
        });
    }

    public function down()
    {
        Schema::table('auction_settings', function (Blueprint $table) {
            $table->string('key')->default(null)->change();
        });
    }
};

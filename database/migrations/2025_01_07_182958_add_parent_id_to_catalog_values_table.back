<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('catalog_values', function (Blueprint $table) {
            // Agregar la columna parent_id para relacionar Modelos con Marcas
            $table->unsignedBigInteger('parent_id')->nullable()->after('catalog_type_id');

            // Definir la clave foránea para parent_id
            $table->foreign('parent_id')
                ->references('id')
                ->on('catalog_values')
                ->onDelete('cascade'); // Cascada para eliminar modelos si la marca se elimina
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('catalog_values', function (Blueprint $table) {
            // Eliminar la clave foránea y la columna parent_id
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};

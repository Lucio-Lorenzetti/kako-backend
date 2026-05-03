<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Agregamos el booleano para marcar si el turno sufrió cambios
            $table->boolean('retrasado')->default(false)->after('hora');
        });
    }

    public function down()
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('retrasado');
        });
    }
};

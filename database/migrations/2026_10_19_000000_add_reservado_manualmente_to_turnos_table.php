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
        Schema::table('turnos', function (Blueprint $table) {
            // Marca permanente: no se borra cuando el cron pasa el turno a
            // "inactivo" por fecha vencida, para poder contar el historial
            // de cargas manuales de admin aunque ya no esté "reservado".
            $table->boolean('reservado_manualmente')->default(false)->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('reservado_manualmente');
        });
    }
};

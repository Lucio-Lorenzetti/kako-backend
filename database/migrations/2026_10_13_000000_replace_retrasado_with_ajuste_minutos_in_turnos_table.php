<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Reemplaza el booleano 'retrasado' por un valor con signo que admite
            // tres estados respecto al horario original: -30 (adelantado), 0 (original), 30 (retrasado).
            $table->tinyInteger('ajuste_minutos')->default(0)->after('hora');
        });

        DB::table('turnos')->where('retrasado', true)->update(['ajuste_minutos' => 30]);

        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('retrasado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->boolean('retrasado')->default(false)->after('hora');
        });

        DB::table('turnos')->where('ajuste_minutos', '!=', 0)->update(['retrasado' => true]);

        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('ajuste_minutos');
        });
    }
};

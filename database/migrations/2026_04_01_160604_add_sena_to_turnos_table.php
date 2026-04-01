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
            // Usamos decimal para precisión de dinero
            $table->decimal('sena', 8, 2)->default(0)->after('precio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Eliminamos la columna si se revierte la migración
            $table->dropColumn('sena');
        });
    }
};

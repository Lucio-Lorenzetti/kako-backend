<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            if (!Schema::hasColumn('reservas', 'estado')) {
                $table->enum('estado', ['pendiente','pagado','cancelada'])->default('pendiente')->after('turno_id');
            }
            if (!Schema::hasColumn('reservas', 'referencia_pago')) {
                $table->string('referencia_pago')->nullable()->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            if (Schema::hasColumn('reservas', 'referencia_pago')) {
                $table->dropColumn('referencia_pago');
            }
            if (Schema::hasColumn('reservas', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};

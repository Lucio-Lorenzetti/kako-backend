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
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('turno_id')->constrained()->onDelete('cascade');

            // Datos del jugador
            $table->string('nombre_jugador'); // histórico, viene de users.name
            $table->string('whatsapp');

            // Datos de la reserva
            $table->unsignedTinyInteger('cantidad_jugadores')->default(1);
            $table->boolean('necesita_paleta')->default(false);
            $table->boolean('buscar_pareja')->default(false);

            // Estado y pago
            $table->decimal('precio_total', 8, 2)->nullable();
            $table->string('referencia_pago')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};

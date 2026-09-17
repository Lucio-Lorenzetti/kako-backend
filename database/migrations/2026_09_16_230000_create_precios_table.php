<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios', function (Blueprint $table) {
            $table->id();
            $table->enum('cancha', ['Interior', 'Exterior'])->unique();
            $table->decimal('precio', 8, 2)->default(0);
            $table->decimal('sena', 8, 2)->default(0);
            $table->timestamps();
        });

        // Precio de referencia único e independiente de los turnos generados,
        // para que borrar/generar turnos nunca lo afecte.
        $precioInterior = DB::table('turnos')
            ->where('cancha', 'Interior')
            ->where('precio', '>', 0)
            ->orderByDesc('fecha')
            ->first();

        $precioExterior = DB::table('turnos')
            ->where('cancha', 'Exterior')
            ->where('precio', '>', 0)
            ->orderByDesc('fecha')
            ->first();

        DB::table('precios')->insert([
            [
                'cancha' => 'Interior',
                'precio' => $precioInterior->precio ?? 0,
                'sena' => $precioInterior->sena ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'cancha' => 'Exterior',
                'precio' => $precioExterior->precio ?? 0,
                'sena' => $precioExterior->sena ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('precios');
    }
};

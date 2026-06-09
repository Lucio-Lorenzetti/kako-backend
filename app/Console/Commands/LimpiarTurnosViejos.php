<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LimpiarTurnosViejos extends Command
{
    protected $signature = 'turnos:limpiar-viejos';
    protected $description = 'Elimina turnos de antes de ayer y sus reservas asociadas';

    public function handle()
    {
        // "Antes de ayer" = fecha < hoy - 1 día
        $fechaLimite = Carbon::now()->subDays(2)->toDateString();

        DB::transaction(function () use ($fechaLimite) {
            // Los turnos tienen onDelete('cascade') en reservas,
            // así que al borrar el turno se borran sus reservas automáticamente
            $borrados = Turno::where('fecha', '<', $fechaLimite)->delete();

            $mensaje = "✔ Turnos eliminados: {$borrados} (anteriores al {$fechaLimite})";
            $this->info($mensaje);
            Log::info($mensaje);
        });

        return Command::SUCCESS;
    }
}
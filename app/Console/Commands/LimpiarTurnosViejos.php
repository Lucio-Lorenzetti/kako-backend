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
    protected $description = 'Elimina los turnos (y sus reservas) del mes calendario anterior completo. Pensado para correr el día 10 de cada mes, dando 10 días de margen para reportes.';

    public function handle()
    {
        $inicioMesPasado = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $finMesPasado    = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        DB::transaction(function () use ($inicioMesPasado, $finMesPasado) {
            // Los turnos tienen onDelete('cascade') en reservas,
            // así que al borrar el turno se borran sus reservas automáticamente
            $borrados = Turno::whereBetween('fecha', [$inicioMesPasado, $finMesPasado])->delete();

            $mensaje = "✔ Turnos eliminados: {$borrados} (mes {$inicioMesPasado} al {$finMesPasado})";
            $this->info($mensaje);
            Log::info($mensaje);
        });

        return Command::SUCCESS;
    }
}
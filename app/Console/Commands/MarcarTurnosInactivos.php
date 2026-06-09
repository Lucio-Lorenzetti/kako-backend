<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarcarTurnosInactivos extends Command
{
    protected $signature = 'turnos:marcar-inactivos';
    protected $description = 'Marca como inactivos los turnos cuyo horario ya pasó';

    public function handle()
    {
        $ahora = Carbon::now();
        $hoy   = $ahora->toDateString();
        $hora  = $ahora->format('H:i:s');

        // Turnos de días anteriores que no estén ya inactivos
        $actualizadosPasados = Turno::where('fecha', '<', $hoy)
            ->where('estado', '!=', 'inactivo')
            ->update(['estado' => 'inactivo']);

        // Turnos de hoy con hora menor a la actual que no estén ya inactivos
        $actualizadosHoy = Turno::where('fecha', $hoy)
            ->where('hora', '<', $hora)
            ->where('estado', '!=', 'inactivo')
            ->update(['estado' => 'inactivo']);

        $total = $actualizadosPasados + $actualizadosHoy;
        $mensaje = "✔ Turnos marcados inactivos: {$total} (Fecha: {$hoy} Hora: {$hora})";
        
        $this->info($mensaje);
        Log::info($mensaje);

        return Command::SUCCESS;
    }
}
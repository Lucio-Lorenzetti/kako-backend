<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerarTurnosMensuales extends Command
{
    protected $signature = 'turnos:generar-mensual {mes? : Mes a generar (YYYY-MM)}';
    protected $description = 'Genera los turnos de un mes determinado con precios correctos';

    public function handle()
    {
        $mesParametro = $this->argument('mes');

        if ($mesParametro) {
            try {
                $inicioMes = Carbon::createFromFormat('Y-m', $mesParametro)->startOfMonth();
                $finMes    = Carbon::createFromFormat('Y-m', $mesParametro)->endOfMonth();
            } catch (\Exception $e) {
                $this->error('Formato de mes inválido. Usar YYYY-MM');
                return Command::FAILURE;
            }
        } else {
            $hoy = Carbon::now();
            // Si es día 1, generamos todo el mes. Si no, desde hoy a fin de mes.
            $inicioMes = ($hoy->day == 1) ? $hoy->copy()->startOfMonth() : $hoy->copy();
            $finMes = $hoy->copy()->endOfMonth();
        }

        $horarios = ['13:00:00', '14:30:00', '16:00:00', '17:30:00', '18:00:00', '19:30:00', '21:00:00', '22:30:00'];
        $canchas = ['Interior'];

        // Obtener últimos precios y señas
        $preciosPorCancha = [];
        foreach ($canchas as $cancha) {
            $ultimo = Turno::where('cancha', $cancha)
                ->whereNotNull('precio')
                ->orderByDesc('fecha')
                ->first();
            
            $preciosPorCancha[$cancha] = [
                'precio' => $ultimo->precio ?? 0,
                'sena'   => $ultimo->sena ?? 0
            ];
        }

        $creados = 0;

        // Bucle de generación
        for ($fecha = $inicioMes->copy(); $fecha->lte($finMes); $fecha->addDay()) {
            foreach ($horarios as $hora) {
                foreach ($canchas as $cancha) {
                    $existe = Turno::where('fecha', $fecha->toDateString())
                        ->where('hora', $hora)
                        ->where('cancha', $cancha)
                        ->exists();

                    if ($existe) continue;

                    Turno::create([
                        'fecha'  => $fecha->toDateString(),
                        'hora'   => $hora,
                        'cancha' => $cancha, // Siempre será 'Interior'
                        'estado' => 'disponible',
                        'precio' => $preciosPorCancha[$cancha]['precio'],
                        'sena'   => $preciosPorCancha[$cancha]['sena'],
                    ]);

                    $creados++;
                }
            }
        }

        $mensaje = "✔ Turnos creados: {$creados}. Periodo: {$inicioMes->format('Y-m-d')} al {$finMes->format('Y-m-d')}";
        $this->info($mensaje);
        Log::info($mensaje); // Importante para ver en los logs de Render

        return Command::SUCCESS;
    }
}
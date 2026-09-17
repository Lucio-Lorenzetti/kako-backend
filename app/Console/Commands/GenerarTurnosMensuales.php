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

        $horarios = ['13:00:00', '14:30:00', '16:00:00', '17:30:00', '19:00:00', '20:30:00', '22:00:00'];
        $canchas = ['Interior'];

        // Obtener últimos precios y señas
        $preciosPorCancha = [];
        foreach ($canchas as $cancha) {
            // Un precio en 0 no cuenta como referencia válida: si no lo excluimos,
            // un turno mal cargado en 0 se propagaría para siempre a los meses siguientes.
            $ultimo = Turno::where('cancha', $cancha)
                ->where('precio', '>', 0)
                ->orderByDesc('fecha')
                ->first();

            if (! $ultimo) {
                $mensaje = "No se encontró ningún turno con precio válido para la cancha {$cancha}. Se generarán con precio 0, hay que cargar el precio real desde el panel de admin.";
                $this->warn($mensaje);
                Log::warning($mensaje);
            }

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
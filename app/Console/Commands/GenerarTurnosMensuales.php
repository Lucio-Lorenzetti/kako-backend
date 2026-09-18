<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use App\Models\Precio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerarTurnosMensuales extends Command
{
    protected $signature = 'turnos:generar-mensual {mes? : Generar un mes puntual (YYYY-MM) en vez de la ventana rodante}';
    protected $description = 'Genera los turnos de los próximos 7 días (ventana rodante) o de un mes puntual si se pasa el parámetro';

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
            // Ventana rodante: se corre todos los días y cubre desde HOY hasta
            // dentro de 7 días (incluir hoy es lo que la hace autorreparable
            // también para el día actual, no solo para el resto de la semana,
            // si algo llegara a borrar los turnos de hoy por error).
            $hoy = Carbon::now();
            $inicioMes = $hoy->copy();
            $finMes = $hoy->copy()->addDays(7);
        }

        $horarios = ['13:00:00', '14:30:00', '16:00:00', '17:30:00', '19:00:00', '20:30:00', '22:00:00'];
        $canchas = ['Interior'];

        // Precio de referencia: viene de la tabla "precios" (independiente de los
        // turnos existentes), así borrar/generar turnos nunca lo puede afectar.
        $preciosPorCancha = [];
        foreach ($canchas as $cancha) {
            $precio = Precio::paraCancha($cancha);

            if ($precio->precio <= 0) {
                $mensaje = "El precio configurado para la cancha {$cancha} es 0. Se generarán los turnos con precio 0, hay que cargar el precio real desde el panel de admin.";
                $this->warn($mensaje);
                Log::warning($mensaje);
            }

            $preciosPorCancha[$cancha] = [
                'precio' => $precio->precio,
                'sena'   => $precio->sena,
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
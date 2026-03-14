<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use Carbon\Carbon;

class GenerarTurnosMensuales extends Command
{
    /**
     * Si no se pasa el parámetro, genera el mes siguiente.
     * Ejemplo:
     * php artisan turnos:generar-mensual 2025-12
     */
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

            // 🔹 Si estamos el día 1 del mes, generamos TODO el mes
            if ($hoy->day == 1) {
                $inicioMes = $hoy->copy()->startOfMonth();
            } else {
                // 🔹 Si es cualquier otro día, generamos solo desde hoy hasta fin de mes
                $inicioMes = $hoy->copy();
            }

            $finMes = $hoy->copy()->endOfMonth();
        }

        $horarios = ['13:00:00', '14:30:00', '16:00:00', '17:30:00',
                    '18:00:00', '19:30:00', '21:00:00', '22:30:00'];
        $canchas = ['Interior', 'Exterior'];

        // 🔹 Obtener precios actuales por cancha
        $preciosPorCancha = [];
        foreach ($canchas as $cancha) {
            $preciosPorCancha[$cancha] = Turno::where('cancha', $cancha)
                ->whereNotNull('precio')
                ->orderByDesc('fecha')
                ->value('precio') ?? 0;
        }

        $creados = 0;

        // 🔹 Generación de turnos
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
                        'cancha' => $cancha,
                        'estado' => 'disponible',
                        'precio' => $preciosPorCancha[$cancha],
                    ]);

                    $creados++;
                }
            }
        }

        $this->info("✔ Turnos creados: {$creados}");
        $this->info("📅 Mes generado desde: {$inicioMes->format('Y-m-d')} hasta {$finMes->format('Y-m-d')}");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Turno;
use Carbon\Carbon;

class CrearTurnosSemana extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'turnos:crear-semana';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea automáticamente los turnos de la semana';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $turnosSemana = [
            'Lunes'    => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Martes'   => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Miércoles' => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Jueves'   => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Viernes'  => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Sábado'   => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
            'Domingo'  => ['13:00', '14:30', '16:00', '17:30', '18:00', '19:30', '21:00', '22:30'],
        ];

        // Limpiar turnos futuros existentes si querés evitar duplicados
        Turno::where('fecha', '>=', Carbon::today())->delete();

        foreach ($turnosSemana as $dia => $horarios) {
            for ($i = 0; $i < 7; $i++) {
                $fecha = Carbon::today()->addDays($i);
                if ($fecha->locale('es')->isoFormat('dddd') == strtolower($dia)) {
                    foreach ($horarios as $hora) {
                        Turno::firstOrCreate(
                            [
                                'fecha' => $fecha->format('Y-m-d'),
                                'hora'  => $hora,
                            ],
                            [
                                'estado' => 'disponible'
                            ]
                        );
                    }
                }
            }
        }
        $this->info('Turnos de la semana creados correctamente.');
    }
}

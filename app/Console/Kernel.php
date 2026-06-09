<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Genera turnos el día 1 de cada mes
        $schedule->command('turnos:generar-mensual')
            ->monthlyOn(1, '00:00')
            ->withoutOverlapping();

        // Marca turnos pasados como inactivos — cada 30 minutos
        $schedule->command('turnos:marcar-inactivos')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // Limpia turnos viejos — todos los días a las 23:59
        $schedule->command('turnos:limpiar-viejos')
            ->dailyAt('23:59')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
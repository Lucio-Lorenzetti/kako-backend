<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Turno;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;

class TurnoSeeder extends Seeder
{
    public function run()
    {
        $canchas = ['Interior','Exterior'];

        for ($i=0; $i<10; $i++) { // 10 días
            foreach ($canchas as $cancha) {
                for ($h=10; $h<=20; $h+=2) { // horarios: 10,12,14,16,18,20
                    Turno::create([
                        'fecha' => Carbon::today()->addDays($i),
                        'hora' => sprintf("%02d:00:00", $h),
                        'estado' => 'disponible',
                        'precio' => 1200,
                        'cancha' => $cancha,
                    ]);
                }
            }
        }
    }
}
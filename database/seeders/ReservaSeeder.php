<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Turno;

class ReservaSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('role','user')->first();
        $turno = Turno::where('estado','disponible')->first();

        if ($user && $turno) {
            Reserva::create([
                'user_id' => $user->id,
                'turno_id' => $turno->id,
                'estado' => 'pendiente',
                'referencia_pago' => null,
            ]);

            $turno->update(['estado' => 'reservado']);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Models\Precio; 
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    public function show()
    {
        $turnos = Turno::orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $interior = Precio::paraCancha('Interior');
        $exterior = Precio::paraCancha('Exterior');

        return response()->json([
            'turnos' => $turnos,
            'precios' => [
                'interior' => $interior->precio,
                'sena_interior' => $interior->sena,
                'exterior' => $exterior->precio,
                'sena_exterior' => $exterior->sena,
            ]
        ]);
    }

    public function getPrecios()
    {
        $interior = Precio::paraCancha('Interior');
        $exterior = Precio::paraCancha('Exterior');

        return response()->json([
            'interior' => $interior->precio,
            'sena_interior' => $interior->sena,
            'requiere_4_jugadores_interior' => $interior->requiere_4_jugadores,
            'exterior' => $exterior->precio,
            'sena_exterior' => $exterior->sena,
            'requiere_4_jugadores_exterior' => $exterior->requiere_4_jugadores,
        ]);
    }
}
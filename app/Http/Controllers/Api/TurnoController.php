<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Models\Precio; 
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    // Método para los turnos
    public function show()
    {
        return Turno::orderBy('fecha')
            ->orderBy('hora')
            ->get();
    }

    public function getPrecios()
    {
        $interior = \App\Models\Turno::where('cancha', 'Interior')->latest()->first();
        $exterior = \App\Models\Turno::where('cancha', 'Exterior')->latest()->first();

        return response()->json([
            'interior' => $interior ? $interior->precio : 0,
            'sena_interior' => $interior ? $interior->sena : 0,
            'exterior' => $exterior ? $exterior->precio : 0,
            'sena_exterior' => $exterior ? $exterior->sena : 0,
        ]);
    }
}
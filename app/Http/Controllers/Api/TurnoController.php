<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
// Importa el modelo donde guardas los precios, por ejemplo:
use App\Models\Precio; 
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    public function show()
    {
        $turnos = Turno::orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $precios = Precio::first(); 

        return response()->json([
            'turnos' => $turnos,
            'precios' => [
                'interior' => $precios->interior ?? 0,
                'sena_interior' => $precios->sena_interior ?? 0,
                'exterior' => $precios->exterior ?? 0,
                'sena_exterior' => $precios->sena_exterior ?? 0,
            ]
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    // Listar turnos disponibles
   public function disponibles()
    {
        // Devuelve solo los turnos que no están reservados
        $turnosDisponibles = Turno::where('estado', 'disponible')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        return response()->json($turnosDisponibles);
    }
}

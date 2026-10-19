<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Http\Request;

class EstadisticasController extends Controller
{
    // Estadísticas de uso del sistema: cuánto reservan los clientes (vía pago real)
    // vs. cuánto cargan los admins a mano (cambiando el estado del turno directo).
    public function usoSistema(Request $request)
    {
        if ($request->user()->role !== 'developer') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $reservasClienteTotal = Reserva::count();

        $turnosReservados = Turno::where('estado', 'reservado');

        $turnosReservadosClienteActual = (clone $turnosReservados)->whereHas('reservas')->count();
        $turnosReservadosManualActual = (clone $turnosReservados)->whereDoesntHave('reservas')->count();

        return response()->json([
            'reservas_cliente_total' => $reservasClienteTotal,
            'turnos_reservados_cliente_actual' => $turnosReservadosClienteActual,
            'turnos_reservados_manual_actual' => $turnosReservadosManualActual,
        ]);
    }
}

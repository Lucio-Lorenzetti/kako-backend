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

        return response()->json([
            // Total histórico de reservas hechas por clientes (pago real),
            // sin importar si el turno ya venció o cambió de estado.
            'reservas_cliente_total' => Reserva::count(),

            // Total histórico de turnos que un admin marcó "reservado" a mano
            // desde el panel. Queda guardado para siempre en el turno, el
            // cron que vence turnos viejos no borra esta marca.
            'turnos_reservados_manual_total' => Turno::where('reservado_manualmente', true)->count(),
        ]);
    }
}

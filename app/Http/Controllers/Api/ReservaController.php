<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    // Listar todas las reservas (solo admin)
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $reservas = Reserva::with('turno', 'user')
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json($reservas);
    }

    // Crear una nueva reserva (usuario autenticado)
    public function store(Request $request)
    {
        $request->validate([
            'turno_id' => 'required|exists:turnos,id',
        ]);

        $turno = Turno::findOrFail($request->turno_id);

        // Chequeo que el turno esté disponible
        if ($turno->estado !== 'disponible') {
            return response()->json(['error' => 'El turno no está disponible'], 400);
        }

        // Evitar que el usuario reserve dos veces el mismo turno
        $existe = Reserva::where('user_id', auth()->id())
            ->where('turno_id', $request->turno_id)
            ->where('estado', '!=', 'cancelada')
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya tenés una reserva para este turno'], 400);
        }

        // No permitir reservar turnos en el pasado
        if ($turno->fecha < now()->toDateString() ||
            ($turno->fecha == now()->toDateString() && $turno->hora < now()->format('H:i:s'))) {
            return response()->json(['error' => 'No se puede reservar un turno en el pasado'], 400);
        }

        $reserva = null;

        // Transacción para mantener consistencia
        DB::transaction(function () use ($turno, &$reserva) {
            $reserva = Reserva::create([
                'user_id' => auth()->id(),
                'turno_id' => $turno->id,
            ]);

            $turno->update(['estado' => 'reservado']);
        });

        return response()->json([
            'message' => 'Reserva creada con éxito',
            'reserva' => $reserva
        ], 201);
    }

    // Ver una reserva específica
    public function show($id)
    {
        $reserva = Reserva::with('turno', 'user')->find($id);

        if (!$reserva) {
            return response()->json(['error' => 'Reserva no encontrada'], 404);
        }

        return response()->json($reserva);
    }

    // Cancelar propia reserva
    public function cancelar($id)
    {
        $reserva = Reserva::with('turno')->findOrFail($id);

        if ($reserva->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado para cancelar esta reserva'], 403);
        }

        if ($reserva->estado === 'cancelada') {
            return response()->json(['error' => 'La reserva ya está cancelada'], 400);
        }

        // Transacción: cancelar reserva + liberar turno
        DB::transaction(function () use ($reserva) {
            $reserva->estado = 'cancelada';
            $reserva->save();

            if ($reserva->turno) {
                $reserva->turno->update(['estado' => 'disponible']);
            }
        });

        return response()->json(['message' => 'Reserva cancelada correctamente'], 200);
    }

    // Listar reservas del usuario autenticado
    public function mias()
    {
        if (auth()->user()->role === 'admin') {
            return response()->json(['error' => 'Los administradores deben usar /admin/reservas'], 403);
        }

        $reservas = Reserva::where('user_id', auth()->id())
                    ->with('turno') 
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json($reservas);
    }
}

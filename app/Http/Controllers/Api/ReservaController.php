<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    // Listar reservas del usuario
    public function index($userId)
    {
        $reservas = Reserva::where('user_id', $userId)
            ->with('turno') // para traer el turno reservado
            ->get();

        return response()->json($reservas);
    }

    // Crear una nueva reserva
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'turno_id' => 'required|exists:turnos,id',
        ]);

        $turno = Turno::findOrFail($request->turno_id);

        // Chequeo que el turno esté disponible
        if ($turno->estado !== 'disponible') {
            return response()->json(['error' => 'El turno no está disponible'], 400);
        }

        // Crear la reserva
        $reserva = Reserva::create([
            'user_id' => $request->user_id,
            'turno_id' => $request->turno_id,
        ]);

        // Cambiar el estado del turno a reservado
        $turno->update(['estado' => 'reservado']);

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
}

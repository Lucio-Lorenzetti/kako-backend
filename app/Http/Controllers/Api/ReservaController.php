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
            'whatsapp' => 'required|string|max:20',
            'cantidad_jugadores' => 'required|integer|min:1|max:4',
            'necesita_paleta' => 'required|boolean',
            'buscar_pareja' => 'required|boolean',
        ]);

        $turno = Turno::findOrFail($request->turno_id);

        if ($turno->estado !== 'disponible') {
            return response()->json(['error' => 'El turno no está disponible o ya fue reservado'], 400);
        }

        $existe = Reserva::where('user_id', auth()->id())
            ->where('turno_id', $request->turno_id)
            ->where('estado', '!=', 'cancelada')
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya tenés una reserva activa para este turno'], 400);
        }

        if ($turno->fecha < now()->toDateString() ||
            ($turno->fecha == now()->toDateString() && $turno->hora < now()->format('H:i:s'))) {
            return response()->json(['error' => 'No se puede reservar un turno en el pasado'], 400);
        }

        try {
            DB::beginTransaction();

            // 💰 Calcular el precio total
            $precioTotal = $turno->precio_por_persona * $request->cantidad_jugadores;

            // Crear la reserva
            $reserva = Reserva::create([
                'user_id' => auth()->id(),
                'turno_id' => $turno->id,
                'nombre_jugador' => auth()->user()->name,
                'whatsapp' => $request->whatsapp,
                'cantidad_jugadores' => $request->cantidad_jugadores,
                'necesita_paleta' => $request->necesita_paleta,
                'buscar_pareja' => $request->buscar_pareja,
                'precio_total' => $precioTotal, // 👈 agregado
                'estado' => 'pendiente'
            ]);

            // Cambiar estado del turno
            $turno->update(['estado' => 'reservado']);

            DB::commit();

            return response()->json([
                'message' => 'Reserva creada con éxito',
                'reserva' => $reserva
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error al crear la reserva: " . $e->getMessage());
            return response()->json(['error' => 'Error interno al procesar la reserva'], 500);
        }
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

        try {
            DB::beginTransaction();

            // Guardar turno antes de cambiar estado
            $turno = $reserva->turno;

            $reserva->estado = 'cancelada';
            $reserva->save();

            // Si había un turno asociado, dejarlo disponible
            if ($turno) {
                $turno->update(['estado' => 'disponible']);
            }

            DB::commit();
            return response()->json(['message' => 'Reserva cancelada correctamente'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error cancelando reserva: '.$e->getMessage());
            return response()->json(['error' => 'Error interno'], 500);
        }
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

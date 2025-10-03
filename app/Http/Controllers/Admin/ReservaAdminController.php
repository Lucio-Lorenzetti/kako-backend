<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

class ReservaAdminController extends Controller
{
    // Listar todas las reservas
    public function index()
    {
        $hoy = now()->startOfDay(); // inicio del día actual
        $fin = now()->addDays(6)->endOfDay(); // fin del sexto día
    
        $reservas = Reserva::with('user', 'turno')
            ->whereHas('turno', function ($q) use ($hoy, $fin) {
                $q->whereDate('fecha', '>=', $hoy)
                  ->whereDate('fecha', '<=', $fin);
            })
            ->orderBy('created_at', 'asc') // para que salgan en orden cronológico
            ->get()
            ->map(function ($reserva) {
                return [
                    'id' => $reserva->id,
                    'estado' => $reserva->estado,
                    'nombre_jugador' => $reserva->nombre_jugador,
                    'whatsapp' => $reserva->whatsapp,
                    'cantidad_jugadores' => $reserva->cantidad_jugadores,
                    'necesita_paleta' => $reserva->necesita_paleta,
                    'buscar_pareja' => $reserva->buscar_pareja,
                    'fecha' => $reserva->turno?->fecha,
                    'hora' => $reserva->turno?->hora,
                    'cancha' => $reserva->turno?->cancha,
                    'usuario' => [
                        'id' => $reserva->user?->id,
                        'nombre' => $reserva->user?->name,
                        'celular' => $reserva->user?->celular,
                    ]
                ];
            });

        return response()->json($reservas);
    }

    public function hoy()
    {
        $hoy = now()->startOfDay();

        $reservasHoy = Reserva::with(['user', 'turno'])
            ->whereHas('turno', fn($q) => $q->whereDate('fecha', $hoy))
            ->get()
            ->sortBy(fn($r) => $r->turno->hora)
            ->values();

        return response()->json($reservasHoy);
    }
    // Ver detalle de una reserva específica
    public function show($id)
    {
        return Reserva::with('user', 'turno')->findOrFail($id);
    }

    // Actualizar estado de reserva
    public function update(Request $request, $id)
    {
        $reserva = Reserva::with('turno')->findOrFail($id);

        DB::transaction(function() use ($reserva, $request) {
            $reserva->update($request->only([
                'estado',
                'whatsapp',
                'cantidad_jugadores',
                'necesita_paleta',
                'buscar_pareja'
            ]));

            if ($reserva->estado === 'cancelada' && $reserva->turno) {
                $reserva->turno->update(['estado' => 'disponible']);
            }
        });

        return response()->json($reserva);
    }
    
    // Liberar una reserva → ponerla en cancelada y el turno disponible
    public function liberar($id)
    {
        DB::transaction(function() use ($id, &$reserva) {
            $reserva = Reserva::with('turno')->findOrFail($id);
            $reserva->estado = 'cancelada';
            $reserva->save();

            if ($reserva->turno) {
                $reserva->turno->update(['estado' => 'disponible']);
            }
        });

        return response()->json([
            'message' => 'Reserva liberada y turno disponible',
            'reserva' => $reserva
        ]);
    }

    // Eliminar una reserva
    public function destroy($id)
    {
        $reserva = Reserva::findOrFail($id);
        $reserva->delete();
        return response()->json(null, 204);
    }
}

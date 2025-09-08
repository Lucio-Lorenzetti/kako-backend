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
        return Reserva::with('user', 'turno')
            ->orderBy('created_at', 'desc')
            ->get();
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
            $reserva->update($request->only('estado'));

            if (in_array($reserva->estado, ['cancelada', 'disponible']) && $reserva->turno) {
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reserva;

class ReservaAdminController extends Controller
{
    // Listar todas las reservas
    public function index()
    {
        return Reserva::all();
    }

    // Ver detalle de una reserva específica
    public function show($id)
    {
        return Reserva::findOrFail($id);
    }

    // Actualizar estado de reserva
    public function update(Request $request, $id)
    {
        $reserva = Reserva::findOrFail($id);
        $reserva->update($request->all());
        return response()->json($reserva);
    }

    // Liberar una reserva (por ejemplo cambiar estado a 'disponible')
    public function liberar($id)
    {
        $reserva = Reserva::findOrFail($id);
        $reserva->estado = 'disponible';
        $reserva->save();
        return response()->json($reserva);
    }

    // Eliminar una reserva
    public function destroy($id)
    {
        $reserva = Reserva::findOrFail($id);
        $reserva->delete();
        return response()->json(null, 204);
    }
}

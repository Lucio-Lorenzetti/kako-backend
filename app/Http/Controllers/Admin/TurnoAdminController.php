<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Turno;

class TurnoAdminController extends Controller
{
    // Listar todos los turnos
    public function index()
    {
        return Turno::all();
    }

    // Ver detalle de un turno específico
    public function show($id)
    {
        return Turno::findOrFail($id);
    }

    // Crear un nuevo turno
    public function store(Request $request)
    {
        $turno = Turno::create($request->all());
        return response()->json($turno, 201);
    }

    // Actualizar un turno existente
    public function update(Request $request, $id)
    {
        $turno = Turno::findOrFail($id);
        $turno->update($request->all());
        return response()->json($turno);
    }

    // Eliminar un turno
    public function destroy($id)
    {
        $turno = Turno::findOrFail($id);
        $turno->delete();
        return response()->json(null, 204);
    }
}

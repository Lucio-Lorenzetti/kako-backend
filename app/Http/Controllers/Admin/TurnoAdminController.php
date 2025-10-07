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
        $request->validate([
            'fecha' => 'required|date|after_or_equal:today',
            'hora'  => 'required|date_format:H:i',
            'estado' => 'in:disponible,reservado,inactivo'
        ]);

        $turno = Turno::create($request->all());
        
        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno
        ], 201);
    }

    // Actualizar un turno existente
    public function update(Request $request, $id)
    {
        $request->validate([
            'fecha' => 'sometimes|date|after_or_equal:today',
            'hora'  => 'sometimes|date_format:H:i',
            'estado' => 'sometimes|in:disponible,reservado,inactivo'
        ]);

        $turno = Turno::findOrFail($id);
        $turno->update($request->all());

        return response()->json([
            'message' => 'Turno actualizado correctamente',
            'turno' => $turno
        ], 200);
    }

    public function precios()
    {
        $precioInterior = Turno::where('cancha', 'Interior')->value('precio') ?? 0;
        $precioExterior = Turno::where('cancha', 'Exterior')->value('precio') ?? 0;

        return response()->json([
            'interior' => $precioInterior,
            'exterior' => $precioExterior
        ]);
    }


    //Actualizar Precio
    public function updatePrecioPorCancha(Request $request, $cancha)
    {
        $request->validate([
            'precio' => 'required|numeric|min:0',
        ]);

        // Validar que la cancha sea válida
        if (!in_array($cancha, ['Interior', 'Exterior'])) {
            return response()->json(['error' => 'Cancha inválida'], 422);
        }

        // Actualiza todos los turnos de esa cancha
        Turno::where('cancha', $cancha)->update([
            'precio' => $request->precio,
        ]);

        return response()->json([
            'message' => "Precio actualizado correctamente para todos los turnos de la cancha {$cancha}",
        ], 200);
    }



    // Eliminar un turno
    public function destroy($id)
    {
        $turno = Turno::findOrFail($id);
        $turno->delete();
        return response()->json(null, 204);
    }
}

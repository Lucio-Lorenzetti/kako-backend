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
        $interior = Turno::where('cancha', 'Interior')->first();
        $exterior = Turno::where('cancha', 'Exterior')->first();

        return response()->json([
            // Si no existe el registro, devolvemos 0 en lugar de romper
            'interior'      => $interior ? $interior->precio : 0,
            'sena_interior' => $interior ? $interior->sena : 0,
            'exterior'      => $exterior ? $exterior->precio : 0,
            'sena_exterior' => $exterior ? $exterior->sena : 0,
        ]);
    }


    //Actualizar Precio
    public function updatePrecioPorCancha(Request $request, $cancha)
    {
        // Validamos que lleguen ambos, que sean números y min 0
        $request->validate([
            'precio' => 'required|numeric|min:0',
            'sena'   => 'required|numeric|min:0',
        ]);

        // CONTROL LÓGICO: Seña no puede ser mayor al precio
        if ($request->sena > $request->precio) {
            return response()->json([
                'error' => 'La seña no puede ser superior al precio total.'
            ], 422); // Error de validación
        }

        // Validar que la cancha sea válida
        if (!in_array($cancha, ['Interior', 'Exterior'])) {
            return response()->json(['error' => 'Cancha inválida'], 422);
        }

        // Actualiza todos los turnos de esa cancha (Precio y Seña)
        Turno::where('cancha', $cancha)->update([
            'precio' => $request->precio,
            'sena'   => $request->sena, // Guardamos la seña en la BD
        ]);

        return response()->json([
            'message' => "Configuración actualizada para todos los turnos de la cancha {$cancha}",
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

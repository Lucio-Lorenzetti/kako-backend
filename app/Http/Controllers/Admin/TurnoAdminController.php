<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Turno;
use Carbon\Carbon;

class TurnoAdminController extends Controller
{
    // Listar todos los turnos
    public function index() {
        return Turno::where('fecha', '>=', now()->toDateString()) // Solo de hoy en adelante
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();
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

    public function retrasarTurnos(Request $request, $id)
    {
        // 1. Buscamos el turno que el usuario seleccionó para iniciar el retraso
        $turnoBase = Turno::findOrFail($id);
        
        // 2. Buscamos todos los turnos que cumplan tres condiciones:
        //    - Misma fecha que el turno seleccionado.
        //    - Misma cancha (Interior o Exterior).
        //    - Hora igual o posterior a la del turno seleccionado.
        $turnosAfectados = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha) 
            ->where('hora', '>=', $turnoBase->hora)
            ->orderBy('hora', 'asc') // Los procesamos en orden cronológico
            ->get();

        // 3. Iteramos y sumamos los 30 minutos
        foreach ($turnosAfectados as $turno) {
            // Usamos parse() que es más flexible con los formatos de hora (HH:mm o HH:mm:ss)
            $nuevaHora = Carbon::parse($turno->hora)
                ->addMinutes(30)
                ->format('H:i:s');

            $turno->update([
                'hora' => $nuevaHora,                
                'retrasado' => false
            ]);
        }

        return response()->json([
            'message' => "Se han retrasado 30 minutos todos los turnos de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}",
            'turnos_actualizados' => $turnosAfectados->count()
        ], 200);
    }

    public function adelantarTurnos(Request $request, $id)
    {
        // 1. Buscamos el turno base
        $turnoBase = Turno::findOrFail($id);
        
        // 2. Buscamos todos los turnos del mismo día, cancha y horario igual o posterior
        $turnosAfectados = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha) 
            ->where('hora', '>=', $turnoBase->hora)
            ->orderBy('hora', 'asc')
            ->get();

        // 3. Iteramos y restamos los 30 minutos
        foreach ($turnosAfectados as $turno) {
            $nuevaHora = Carbon::parse($turno->hora)
                ->subMinutes(30) // <--- Aquí usamos subMinutes para volver atrás
                ->format('H:i:s');

            $turno->update([
                'hora' => $nuevaHora,
                'retrasado' => false
            ]);
        }

        return response()->json([
            'message' => "Se han adelantado 30 minutos (corrección) los turnos de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}",
            'turnos_actualizados' => $turnosAfectados->count()
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

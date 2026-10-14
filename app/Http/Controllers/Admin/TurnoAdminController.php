<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Turno;
use App\Models\Precio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TurnoAdminController extends Controller
{
    // Duración de cada turno en la grilla horaria del negocio (13:00, 14:30, 16:00, ...)
    private const DURACION_TURNO_MINUTOS = 90;
    private const AJUSTE_MAXIMO_MINUTOS = 30;

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

        // No se puede retrasar un turno que ya está en su límite de retraso
        if ($turnoBase->ajuste_minutos >= self::AJUSTE_MAXIMO_MINUTOS) {
            return response()->json([
                'error' => 'Este turno ya fue retrasado 30 minutos. Volvé a su horario original antes de retrasarlo de nuevo.',
            ], 422);
        }

        // 2. Buscamos todos los turnos que cumplan tres condiciones:
        //    - Misma fecha que el turno seleccionado.
        //    - Misma cancha (Interior o Exterior).
        //    - Hora igual o posterior a la del turno seleccionado.
        $turnosAfectados = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha)
            ->where('hora', '>=', $turnoBase->hora)
            ->orderBy('hora', 'asc') // Los procesamos en orden cronológico
            ->get();

        // Si algún turno de la cascada ya está en su límite, abortamos antes de tocar nada
        foreach ($turnosAfectados as $turno) {
            if ($turno->ajuste_minutos >= self::AJUSTE_MAXIMO_MINUTOS) {
                return response()->json([
                    'error' => "No se puede retrasar: el turno de las {$turno->hora} ya está retrasado al máximo permitido.",
                ], 422);
            }
        }

        // 3. Sumamos los 30 minutos a todos los turnos afectados dentro de una transacción
        DB::transaction(function () use ($turnosAfectados) {
            foreach ($turnosAfectados as $turno) {
                $turno->update([
                    'hora' => Carbon::parse($turno->hora)->addMinutes(30)->format('H:i:s'),
                    'ajuste_minutos' => $turno->ajuste_minutos + 30,
                ]);
            }
        });

        return response()->json([
            'message' => "Se han retrasado 30 minutos todos los turnos de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}",
            'turnos_actualizados' => $turnosAfectados->count()
        ], 200);
    }

    public function adelantarTurnos(Request $request, $id)
    {
        // 1. Buscamos el turno base
        $turnoBase = Turno::findOrFail($id);

        // No se puede adelantar un turno que ya está en su límite de adelanto
        if ($turnoBase->ajuste_minutos <= -self::AJUSTE_MAXIMO_MINUTOS) {
            return response()->json([
                'error' => 'Este turno ya fue adelantado 30 minutos. Volvé a su horario original antes de adelantarlo de nuevo.',
            ], 422);
        }

        // 2. Buscamos todos los turnos del mismo día, cancha y horario igual o posterior
        $turnosAfectados = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha)
            ->where('hora', '>=', $turnoBase->hora)
            ->orderBy('hora', 'asc')
            ->get();

        // Si algún turno de la cascada ya está en su límite, abortamos antes de tocar nada
        foreach ($turnosAfectados as $turno) {
            if ($turno->ajuste_minutos <= -self::AJUSTE_MAXIMO_MINUTOS) {
                return response()->json([
                    'error' => "No se puede adelantar: el turno de las {$turno->hora} ya está adelantado al máximo permitido.",
                ], 422);
            }
        }

        // Al adelantar el turno base se acerca al turno anterior: validamos que no se solapen
        $turnoAnterior = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha)
            ->where('hora', '<', $turnoBase->hora)
            ->orderBy('hora', 'desc')
            ->first();

        if ($turnoAnterior) {
            $nuevaHoraBase = Carbon::parse($turnoBase->hora)->subMinutes(30);
            $finTurnoAnterior = Carbon::parse($turnoAnterior->hora)->addMinutes(self::DURACION_TURNO_MINUTOS);

            if ($nuevaHoraBase->lt($finTurnoAnterior)) {
                return response()->json([
                    'error' => "No se puede adelantar: se solaparía con el turno de las {$turnoAnterior->hora}.",
                ], 422);
            }
        }

        // 3. Restamos los 30 minutos a todos los turnos afectados dentro de una transacción
        DB::transaction(function () use ($turnosAfectados) {
            foreach ($turnosAfectados as $turno) {
                $turno->update([
                    'hora' => Carbon::parse($turno->hora)->subMinutes(30)->format('H:i:s'),
                    'ajuste_minutos' => $turno->ajuste_minutos - 30,
                ]);
            }
        });

        return response()->json([
            'message' => "Se han adelantado 30 minutos los turnos de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}",
            'turnos_actualizados' => $turnosAfectados->count()
        ], 200);
    }

    public function precios()
    {
        $interior = Precio::paraCancha('Interior');
        $exterior = Precio::paraCancha('Exterior');

        return response()->json([
            'interior'      => $interior->precio,
            'sena_interior' => $interior->sena,
            'requiere_4_jugadores_interior' => $interior->requiere_4_jugadores,
            'exterior'      => $exterior->precio,
            'sena_exterior' => $exterior->sena,
            'requiere_4_jugadores_exterior' => $exterior->requiere_4_jugadores,
        ]);
    }


    //Actualizar Precio
    public function updatePrecioPorCancha(Request $request, $cancha)
    {
        // Validamos que lleguen ambos, que sean números y min 0
        $request->validate([
            'precio' => 'required|numeric|min:0',
            'sena'   => 'required|numeric|min:0',
            'requiere_4_jugadores' => 'sometimes|boolean',
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

        // Fuente de verdad para los próximos turnos que se generen
        $datosPrecio = ['precio' => $request->precio, 'sena' => $request->sena];
        if ($request->has('requiere_4_jugadores')) {
            $datosPrecio['requiere_4_jugadores'] = $request->boolean('requiere_4_jugadores');
        }

        Precio::updateOrCreate(['cancha' => $cancha], $datosPrecio);

        // Y reflejamos el cambio en los turnos ya existentes de esa cancha
        Turno::where('cancha', $cancha)->update([
            'precio' => $request->precio,
            'sena'   => $request->sena,
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

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
    private const AJUSTE_MAXIMO_MINUTOS = 30;
    private const ESTADO_RESERVADO = 'reservado';

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

        // Si el turno inmediatamente posterior al elegido está reservado, no se puede
        // iniciar el retraso (el turno elegido quedaría pegado a uno ocupado)
        $siguienteDeBase = $turnosAfectados[1] ?? null;
        if ($siguienteDeBase && $siguienteDeBase->estado === self::ESTADO_RESERVADO) {
            return response()->json([
                'error' => "No se puede retrasar: el turno de las {$siguienteDeBase->hora} está reservado.",
            ], 422);
        }

        // 3. Sumamos los 30 minutos en cascada. Un turno reservado (que no sea el elegido)
        //    nunca se mueve: frena la cascada ahí para no pisarlo ni acercarse de más.
        $actualizados = 0;
        $detenidoEn = null;

        DB::transaction(function () use ($turnosAfectados, &$actualizados, &$detenidoEn) {
            foreach ($turnosAfectados as $i => $turno) {
                if ($i > 0) {
                    $siguiente = $turnosAfectados[$i + 1] ?? null;
                    $sinEspacio = $turno->estado === self::ESTADO_RESERVADO
                        || $turno->ajuste_minutos >= self::AJUSTE_MAXIMO_MINUTOS
                        || ($siguiente && $siguiente->estado === self::ESTADO_RESERVADO);

                    if ($sinEspacio) {
                        $detenidoEn = $turno->hora;
                        break;
                    }
                }

                $turno->update([
                    'hora' => Carbon::parse($turno->hora)->addMinutes(30)->format('H:i:s'),
                    'ajuste_minutos' => $turno->ajuste_minutos + 30,
                ]);
                $actualizados++;
            }
        });

        $mensaje = "Se retrasaron 30 minutos {$actualizados} turno(s) de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}.";
        if ($detenidoEn !== null) {
            $mensaje .= " El ajuste en cascada se detuvo antes del turno de las {$detenidoEn} porque interfiere con un turno reservado.";
        }

        return response()->json([
            'message' => $mensaje,
            'turnos_actualizados' => $actualizados,
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

        // Si el turno inmediatamente anterior está reservado, no se puede adelantar
        // (el turno elegido quedaría pegado a uno ocupado)
        $turnoAnterior = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha)
            ->where('hora', '<', $turnoBase->hora)
            ->orderBy('hora', 'desc')
            ->first();

        if ($turnoAnterior && $turnoAnterior->estado === self::ESTADO_RESERVADO) {
            return response()->json([
                'error' => "No se puede adelantar: el turno de las {$turnoAnterior->hora} está reservado.",
            ], 422);
        }

        // No se puede adelantar un turno a un horario que ya pasó
        $nuevaHoraBase = Carbon::parse($turnoBase->hora)->subMinutes(30);
        $esHoy = Carbon::parse($turnoBase->fecha)->isSameDay(now());
        if ($esHoy && $nuevaHoraBase->lte(now())) {
            return response()->json([
                'error' => 'No se puede adelantar: el nuevo horario ya pasó.',
            ], 422);
        }

        // 2. Buscamos todos los turnos del mismo día, cancha y horario igual o posterior
        $turnosAfectados = Turno::where('fecha', $turnoBase->fecha)
            ->where('cancha', $turnoBase->cancha)
            ->where('hora', '>=', $turnoBase->hora)
            ->orderBy('hora', 'asc')
            ->get();

        // 3. Restamos los 30 minutos en cascada. Como todos los turnos avanzan hacia
        //    atrás juntos, la distancia entre ellos no cambia; solo frenamos antes de
        //    mover un turno reservado (que no sea el elegido).
        $actualizados = 0;
        $detenidoEn = null;

        DB::transaction(function () use ($turnosAfectados, &$actualizados, &$detenidoEn) {
            foreach ($turnosAfectados as $i => $turno) {
                if ($i > 0 && ($turno->estado === self::ESTADO_RESERVADO || $turno->ajuste_minutos <= -self::AJUSTE_MAXIMO_MINUTOS)) {
                    $detenidoEn = $turno->hora;
                    break;
                }

                $turno->update([
                    'hora' => Carbon::parse($turno->hora)->subMinutes(30)->format('H:i:s'),
                    'ajuste_minutos' => $turno->ajuste_minutos - 30,
                ]);
                $actualizados++;
            }
        });

        $mensaje = "Se adelantaron 30 minutos {$actualizados} turno(s) de la cancha {$turnoBase->cancha} desde las {$turnoBase->hora}.";
        if ($detenidoEn !== null) {
            $mensaje .= " El ajuste en cascada se detuvo en el turno de las {$detenidoEn} porque está reservado.";
        }

        return response()->json([
            'message' => $mensaje,
            'turnos_actualizados' => $actualizados,
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    // Iniciar pago (devuelve info de MercadoPago)
    public function iniciarPago(Request $request, $id)
    {
        $reserva = Reserva::with('turno')->findOrFail($id);

        if ($reserva->estado !== 'pendiente') {
            return response()->json(['error' => 'La reserva ya fue pagada o cancelada'], 400);
        }

        // Aquí se integraría la API de MercadoPago para crear la preferencia de pago
        return response()->json([
            'message' => 'Iniciando pago...',
            'reserva' => $reserva,
            'url_pago' => 'https://www.mercadopago.com/mock-url'
        ]);
    }

    // Webhook de MercadoPago para confirmar pagos
    public function webhook(Request $request)
    {
        $reservaId = $request->input('reserva_id');
        $referenciaPago = $request->input('referencia_pago');
        $estadoPago = $request->input('estado'); // 'aprobado'

        $reserva = Reserva::with('turno')->find($reservaId);
        if (!$reserva || $reserva->estado !== 'pendiente') {
            return response()->json(['message' => 'Reserva no encontrada o ya procesada'], 200);
        }

        if ($estadoPago === 'aprobado') {
            DB::transaction(function () use ($reserva, $referenciaPago) {
                $reserva->estado = 'pagado';
                $reserva->referencia_pago = $referenciaPago;
                $reserva->save();

                if ($reserva->turno) {
                    $reserva->turno->estado = 'reservado';
                    $reserva->turno->save();
                }
            });
        }

        return response()->json(['message' => 'Webhook procesado'], 200);
    }
}

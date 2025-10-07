<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reserva;
use App\Models\Turno;
use MercadoPago\MercadoPagoConfig;

class PagoController extends Controller
{
    public function webhook(Request $request)
    {
        $paymentId = $request->input('data.id');
        if (!$paymentId) {
            return response()->json(['message' => 'No payment id'], 400);
        }

        $accessToken = env('MP_ACCESS_TOKEN');
        if (!$accessToken) {
            \Log::error('MP_ACCESS_TOKEN no configurado');
            return response()->json(['error' => 'Configuración de Mercado Pago inválida'], 500);
        }

        MercadoPagoConfig::setAccessToken($accessToken);
        $client = new \MercadoPago\Client\Payment\PaymentClient();

        try {
            $payment = $client->get($paymentId);

            if (isset($payment->status) && $payment->status === 'approved') {
                $metadata = (array) ($payment->metadata ?? []);

                // Validar campos mínimos en metadata
                if (empty($metadata['turno_id']) || empty($metadata['user_id'])) {
                    \Log::error('Metadata incompleta en webhook MP', $metadata);
                    return response()->json(['error' => 'Metadata incompleta'], 400);
                }

                DB::transaction(function () use ($metadata) {
                    Reserva::create([
                        'turno_id' => $metadata['turno_id'],
                        'user_id' => $metadata['user_id'],
                        'cantidad_jugadores' => $metadata['cantidad_jugadores'] ?? 1,
                        'buscar_pareja' => isset($metadata['buscar_pareja']) ? (bool)$metadata['buscar_pareja'] : false,
                        'whatsapp' => $metadata['whatsapp'] ?? $metadata['telefono'] ?? null,
                        'necesita_paleta' => isset($metadata['necesita_paleta']) ? (bool)$metadata['necesita_paleta'] : false,
                        'nombre_jugador' => $metadata['nombre_jugador'] ?? null,
                        'estado' => 'pagado',
                        'referencia_pago' => $payment->id ?? null,
                    ]);

                    $turno = Turno::find($metadata['turno_id']);
                    if ($turno) {
                        $turno->update(['estado' => 'reservado']);
                    }
                });
            }

            return response()->json(['message' => 'Webhook procesado'], 200);
        } catch (\Exception $e) {
            \Log::error("Error webhook MP: " . $e->getMessage(), ['payload' => $request->all()]);
            return response()->json(['error' => 'Error al procesar webhook'], 500);
        }
    }
}

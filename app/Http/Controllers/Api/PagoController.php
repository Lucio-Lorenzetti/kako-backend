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

        MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));
        $client = new \MercadoPago\Client\Payment\PaymentClient();

        try {
            $payment = $client->get($paymentId);

            if ($payment->status === 'approved') {
                $metadata = (array) $payment->metadata;

                DB::transaction(function () use ($metadata) {
                    Reserva::create([
                        'turno_id' => $metadata['turno_id'],
                        'user_id' => $metadata['user_id'],
                        'jugadores' => $metadata['jugadores'],
                        'busco_pareja' => $metadata['busco_pareja'],
                        'telefono' => $metadata['telefono'],
                        'presta_paletas' => $metadata['presta_paletas'],
                        'estado' => 'pagado',
                    ]);

                    $turno = Turno::find($metadata['turno_id']);
                    if ($turno) {
                        $turno->estado = 'reservado';
                        $turno->save();
                    }
                });
            }

            return response()->json(['message' => 'Webhook procesado'], 200);
        } catch (\Exception $e) {
            \Log::error("Error webhook MP: " . $e->getMessage());
            return response()->json(['error' => 'Error al procesar webhook'], 500);
        }
    }
}

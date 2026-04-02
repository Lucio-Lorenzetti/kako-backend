<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Reserva;
use App\Models\Turno;

class MercadoPagoController extends Controller
{
    /**
     * Crear una preferencia de pago en Mercado Pago.
     */
    public function crearPreferencia(Request $request)
    {
        // 1. Validación estricta
        $request->validate([
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'required|string',
            'turno_id' => 'required|exists:turnos,id',
            'whatsapp' => 'required|string|max:15',
            'cantidad_jugadores' => 'required|in:2,4',
            'buscar_pareja' => 'required|boolean',
            'necesita_paleta' => 'required|boolean',
        ]);

        try {
            $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));
            if (!$mpToken) {
                return response()->json(['error' => 'Token MP no encontrado en env'], 500);
            }

            MercadoPagoConfig::setAccessToken($mpToken);
            $client = new PreferenceClient();

            $user = auth('api')->user() ?? auth()->user();
            
            // Simplificamos los datos para evitar errores de codificación
            $externalData = [
                'turno_id' => (int) $request->turno_id,
                'user_id'  => $user ? $user->id : null,
                'wa'       => substr($request->whatsapp, 0, 15), // Nombre corto
                'jug'      => (int) $request->cantidad_jugadores,
                'nombre'   => $user ? substr($user->name, 0, 20) : 'Invitado'
            ];

            $requestBody = [
                'items' => [
                    [
                        'title'       => (string) $request->descripcion,
                        'quantity'    => 1,
                        'unit_price'  => (float) $request->monto,
                    ]
                ],
                'back_urls' => [
                    'success' => 'https://kakopadel.vercel.app/pago/success',
                    'failure' => 'https://kakopadel.vercel.app/failure',
                ],
                'auto_return'        => 'approved',
                'external_reference' => json_encode($externalData),
                'notification_url'   => 'https://kako-backend.onrender.com/api/mercadopago/webhook',
            ];

            $preference = $client->create($requestBody);
            return response()->json(['id' => $preference->id], 200);

        } catch (\Throwable $e) {
            // ESTO ES LO MÁS IMPORTANTE: Ver qué dice la SDK
            return response()->json([
                'error'   => 'Fallo Mercado Pago',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    /**
     * Manejar webhook de Mercado Pago.
     */
    public function handleWebhook(Request $request)
    {
        $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));
        if (!$mpToken) {
            \Log::error('Webhook recibido pero token de MercadoPago no configurado.');
            return response()->json(['error' => 'Server misconfiguration'], 500);
        }

        MercadoPagoConfig::setAccessToken($mpToken);
        $payload = $request->all();
        \Log::info('Webhook recibido MercadoPago:', $payload);

        try {
            $topic = $payload['topic'] ?? $payload['type'] ?? null;
            $id = $payload['id'] ?? $payload['data']['id'] ?? null;

            if (!$topic || !$id) {
                return response()->json(['status' => 'ignored'], 200);
            }

            if (!in_array(strtolower($topic), ['payment', 'payment_created', 'merchant_order'])) {
                return response()->json(['status' => 'ignored'], 200);
            }

            $paymentClient = new PaymentClient();
            $paymentResource = $paymentClient->get((string) $id);
            $paymentData = json_decode(json_encode($paymentResource), true);

            $status = strtolower($paymentData['status'] ?? '');
            $reference = $paymentData['external_reference'] ?? ($paymentData['metadata']['external_reference'] ?? null);
            $paymentId = $paymentData['id'] ?? null;
            $total = $paymentData['transaction_details']['total_paid_amount'] ?? null;

            if (!$reference) {
                return response()->json(['status' => 'no_reference'], 200);
            }

            $external = json_decode($reference, true);
            if (!is_array($external)) {
                return response()->json(['status' => 'invalid_reference'], 200);
            }

            if (in_array($status, ['approved', 'authorized'])) {
                if (Reserva::where('referencia_pago', $paymentId)->exists()) {
                    return response()->json(['status' => 'already_processed'], 200);
                }

                $reserva = Reserva::create([
                    'user_id' => $external['user_id'] ?? null,
                    'turno_id' => $external['turno_id'] ?? null,
                    'nombre_jugador' => $external['nombre_jugador'] ?? 'Invitado',
                    'whatsapp' => $external['whatsapp'] ?? null,
                    'cantidad_jugadores' => $external['cantidad_jugadores'] ?? null,
                    'necesita_paleta' => $external['necesita_paleta'] ?? false,
                    'buscar_pareja' => $external['buscar_pareja'] ?? false,
                    'estado' => 'pagado',
                    'referencia_pago' => $paymentId,
                    'precio_total' => $total,
                ]);

                if (!empty($external['turno_id'])) {
                    $turno = Turno::find($external['turno_id']);
                    if ($turno) {
                        $turno->estado = 'reservado';
                        $turno->save();
                    }
                }

                return response()->json(['status' => 'reservation_created', 'reserva_id' => $reserva->id], 200);
            }

            return response()->json(['status' => 'not_approved'], 200);

        } catch (\Throwable $e) {
            \Log::error('Error procesando webhook MercadoPago: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'processing_error'], 500);
        }
        
    }
}

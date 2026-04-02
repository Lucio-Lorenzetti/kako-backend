<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Support\Facades\Log;
use App\Models\Reserva;
use App\Models\Turno;

class MercadoPagoController extends Controller
{
    public function crearPreferencia(Request $request)
    {
        try {
            $token = env('MP_ACCESS_TOKEN');
            if (!$token) return response()->json(['error' => 'Token no configurado'], 500);

            MercadoPagoConfig::setAccessToken($token);
            $client = new PreferenceClient();

            $user = auth('api')->user() ?? auth()->user();

            // 1. Preparamos la data que necesitamos recuperar en el Webhook
            $externalData = [
                'turno_id' => $request->turno_id,
                'user_id' => $user ? $user->id : null,
                'whatsapp' => $request->whatsapp,
                'cantidad_jugadores' => $request->cantidad_jugadores,
                'necesita_paleta' => $request->necesita_paleta,
                'buscar_pareja' => $request->buscar_pareja,
                'nombre_jugador' => $user ? $user->name : 'Invitado'
            ];

            // 2. Creamos la preferencia
            $preference = $client->create([
                "items" => [
                    [
                        "title" => (string) $request->descripcion,
                        "quantity" => 1,
                        "unit_price" => (float) $request->monto,
                        "currency_id" => "ARS"
                    ]
                ],
                "back_urls" => [
                    "success" => "https://kakopadel.vercel.app/pago/success",
                    "failure" => "https://kakopadel.vercel.app/failure",
                    "pending" => "https://kakopadel.vercel.app/pending"
                ],
                "auto_return" => "approved",
                // IMPORTANTE: Mandarlo como JSON string
                "external_reference" => json_encode($externalData),
                "notification_url" => "https://kako-backend.onrender.com/api/mercadopago/webhook",
            ]);

            return response()->json(['id' => $preference->id]);

        } catch (\Exception $e) {
            Log::error("Error MP Preferencia: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $token = env('MP_ACCESS_TOKEN');
        if (!$token) return response()->json(['error' => 'No token'], 500);

        MercadoPagoConfig::setAccessToken($token);
        $payload = $request->all();

        try {
            $id = $payload['data']['id'] ?? $payload['id'] ?? null;
            $type = $payload['type'] ?? $payload['topic'] ?? null;

            if ($type !== 'payment' || !$id) {
                return response()->json(['status' => 'ignored'], 200);
            }

            $paymentClient = new PaymentClient();
            $payment = $paymentClient->get($id);
            
            $status = $payment->status;
            $externalReference = $payment->external_reference;

            if ($status === 'approved') {
                $data = json_decode($externalReference, true);

                if (!$data) {
                    Log::error("Webhook: No se pudo decodificar external_reference", [$externalReference]);
                    return response()->json(['error' => 'invalid data'], 200);
                }

                // Evitar duplicados
                if (Reserva::where('referencia_pago', $id)->exists()) {
                    return response()->json(['status' => 'already_done'], 200);
                }

                // Crear Reserva
                $reserva = Reserva::create([
                    'user_id' => $data['user_id'] ?? null,
                    'turno_id' => $data['turno_id'],
                    'nombre_jugador' => $data['nombre_jugador'] ?? 'Invitado',
                    'whatsapp' => $data['whatsapp'],
                    'cantidad_jugadores' => $data['cantidad_jugadores'],
                    'necesita_paleta' => $data['necesita_paleta'] ?? false,
                    'buscar_pareja' => $data['buscar_pareja'] ?? false,
                    'estado' => 'pagado',
                    'referencia_pago' => $id,
                    'precio_total' => $payment->transaction_details->total_paid_amount
                ]);

                // Actualizar Turno
                $turno = Turno::find($data['turno_id']);
                if ($turno) {
                    $turno->update(['estado' => 'reservado']);
                }

                return response()->json(['status' => 'ok'], 200);
            }

            return response()->json(['status' => $status], 200);

        } catch (\Exception $e) {
            Log::error("Webhook Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
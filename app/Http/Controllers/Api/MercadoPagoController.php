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
            if (!$token) return response()->json(['error' => 'Config Error'], 500);

            MercadoPagoConfig::setAccessToken($token);
            $client = new PreferenceClient();

            $user = auth('api')->user() ?? auth()->user();

            // Datos limpios para evitar errores de parseo en MP
            $externalData = [
                'turno_id'           => (int) $request->turno_id,
                'user_id'            => $user ? $user->id : null,
                'whatsapp'           => (string) $request->whatsapp,
                'cantidad_jugadores' => (int) $request->cantidad_jugadores,
                'necesita_paleta'    => (bool) $request->necesita_paleta,
                'buscar_pareja'      => (bool) $request->buscar_pareja,
                'nombre_jugador'     => $user ? (string) $user->name : 'Invitado'
            ];

            $preference = $client->create([
                "items" => [
                    [
                        "title"       => (string) ($request->descripcion ?? "Reserva de Turno"),
                        "quantity"    => 1,
                        "unit_price"  => (float) $request->monto,
                        "currency_id" => "ARS"
                    ]
                ],
                "back_urls" => [
                    "success" => "https://kakopadel.vercel.app/pago/success",
                    "failure" => "https://kakopadel.vercel.app/failure",
                    "pending" => "https://kakopadel.vercel.app/pending"
                ],
                "auto_return"        => "approved",
                "external_reference" => json_encode($externalData),
                "notification_url"   => "https://kako-backend.onrender.com/api/mercadopago/webhook",
            ]);

            return response()->json(['id' => $preference->id]);

        } catch (\Exception $e) {
            Log::error("MP Preference Error: " . $e->getMessage());
            return response()->json(['error' => 'Mercado Pago Failure', 'detail' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        try {
            $token = env('MP_ACCESS_TOKEN');
            MercadoPagoConfig::setAccessToken($token);

            $id = $request->input('data.id') ?? $request->input('id');
            $type = $request->input('type') ?? $request->input('topic');

            if ($type !== 'payment' || !$id) return response()->json(['status' => 'ignored'], 200);

            $paymentClient = new PaymentClient();
            $payment = $paymentClient->get($id);
            
            if ($payment->status === 'approved') {
                $data = json_decode($payment->external_reference, true);
                if (!$data) return response()->json(['status' => 'no_data'], 200);

                if (Reserva::where('referencia_pago', (string)$id)->exists()) {
                    return response()->json(['status' => 'duplicate'], 200);
                }

                $reserva = Reserva::create([
                    'user_id'            => $data['user_id'],
                    'turno_id'           => $data['turno_id'],
                    'nombre_jugador'     => $data['nombre_jugador'],
                    'whatsapp'           => $data['whatsapp'],
                    'cantidad_jugadores' => $data['cantidad_jugadores'],
                    'necesita_paleta'    => $data['necesita_paleta'],
                    'buscar_pareja'      => $data['buscar_pareja'],
                    'estado'             => 'pagado',
                    'referencia_pago'    => (string)$id,
                    'precio_total'       => $payment->transaction_details->total_paid_amount
                ]);

                $turno = Turno::find($data['turno_id']);
                if ($turno) $turno->update(['estado' => 'reservado']);

                return response()->json(['status' => 'ok'], 200);
            }
            return response()->json(['status' => $payment->status], 200);
        } catch (\Exception $e) {
            Log::error("Webhook Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
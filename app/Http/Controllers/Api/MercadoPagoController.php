<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use Illuminate\Support\Facades\Http;

class MercadoPagoController extends Controller
{
    /**
     * Crea una preferencia de pago en Mercado Pago.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearPreferencia(Request $request)
    {
        // Validación: Requerimos los datos esenciales para el pago y la reserva.
        $this->validate($request, [
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'required|string',
            'turno_id' => 'required|exists:turnos,id',
            'whatsapp' => 'required|string|max:15',
            'cantidad_jugadores' => 'required|in:2,4',
            'buscar_pareja' => 'required|boolean',
            'necesita_paleta' => 'required|boolean',
        ]);

    try {
            // Resolve token from config/services.php -> mercadopago.token
            $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));

            if (empty($mpToken)) {
                \Log::error('MercadoPago token missing: check MERCADOPAGO_ACCESS_TOKEN or MP_ACCESS_TOKEN env variables.');
                return response()->json([
                    'error' => 'Mercado Pago access token no configurado. Contactá al administrador.'
                ], 500);
            }

            // Initialize SDK safely
            MercadoPagoConfig::setAccessToken($mpToken);

            // Use PreferenceClient to create the preference
            $client = new PreferenceClient();

            $appUrlBackend = config('app.url');      
            $appUrlFrontend = config('app.frontend_url');   
            $nombreJugador = auth()->check() ? auth()->user()->name : 'Invitado';
            \Log::info('DEBUG URLS:', ['frontend' => $appUrlFrontend, 'backend' => $appUrlBackend]);
            if (empty($appUrlFrontend) || empty($appUrlBackend)) {
                throw new \Exception("APP_URL o FRONTEND_URL no están configuradas en .env.");
            }

            $requestBody = [
                'items' => [
                    [
                        'title' => $request->descripcion,
                        'quantity' => 1,
                        'unit_price' => (float) $request->monto,
                    ]
                ],
                "back_urls" => [
                    "success" => $appUrlFrontend . '/pago/exitoso',
                    "failure" => $appUrlFrontend . '/pago/fallido',
                    "pending" => $appUrlFrontend . '/pago/pendiente'
                ],
                
                //'auto_return' => 'approved',

                //'notification_url' => $appUrlBackend . '/mercadopago/webhook',

                'external_reference' => json_encode([
                    'turno_id' => $request->turno_id,
                    'cantidad_jugadores' => $request->cantidad_jugadores,
                    'buscar_pareja' => $request->buscar_pareja,
                    'whatsapp' => $request->whatsapp,
                    'necesita_paleta' => $request->necesita_paleta,
                    'user_id' => auth()->id(),
                    'nombre_jugador' => $nombreJugador,
                ])
            ];

            $requestOptions = new RequestOptions();

            $preference = $client->create($requestBody, $requestOptions);

            return response()->json([
                'id' => $preference->id
            ], 200);

        } catch (\Throwable $e) {
            // Try to extract HTTP response details from the SDK exception if available
            $extra = [];
            if (method_exists($e, 'getResponse') && ($resp = $e->getResponse())) {
                try {
                    $body = method_exists($resp, 'getBody') ? (string) $resp->getBody() : (string) $resp;
                    $extra['mp_response_body'] = $body;
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $extra['mp_response_json'] = $decoded;
                    }
                } catch (\Throwable $_) {
                    $extra['mp_response_error'] = 'failed_to_read_response_body';
                }
            }

            $extra['trace'] = $e->getTraceAsString();
            \Log::error('Error MercadoPago creando preferencia: ' . $e->getMessage(), $extra);
            // Try HTTP fallback (bypass SDK) to create the preference directly
            try {
                $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));
                $fallback = $this->createPreferenceViaHttp($requestBody, $mpToken);
                if (!empty($fallback) && isset($fallback['id'])) {
                    \Log::info('Preference created via HTTP fallback', ['preference_id' => $fallback['id']]);
                    return response()->json(['id' => $fallback['id']], 200);
                }

                // If the fallback returned a Mercado Pago error body, handle common cases
                if (is_array($fallback) && isset($fallback['message']) && $fallback['message'] === 'invalid_token') {
                    \Log::warning('MercadoPago invalid_token detected', ['fallback' => $fallback]);
                    $bodyForClient = ['error' => 'Token de Mercado Pago inválido. Verificá MP_ACCESS_TOKEN en .env y el entorno (sandbox vs production).'];
                    if (config('app.debug')) {
                        $bodyForClient['mp_response'] = $fallback;
                    }
                    return response()->json($bodyForClient, 401);
                }

                // If fallback didn't return id, include body in logs
                \Log::warning('HTTP fallback for MercadoPago preference did not return id', ['fallback' => $fallback]);
            } catch (\Throwable $_fb) {
                \Log::error('Fallback HTTP MercadoPago error: ' . $_fb->getMessage());
            }

            return response()->json([
                'error' => 'No se pudo generar la preferencia de pago. Intentá nuevamente.',
                'details' => env('APP_DEBUG') ? $e->getMessage() : 'Error interno.'
            ], 500);
        }
    }
    
    
    public function handleWebhook(Request $request)
    {
        // Seguridad: obtener token y configurar SDK
        $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));

        if (empty($mpToken)) {
            \Log::error('Webhook recibido pero MercadoPago token no configurado.');
            return response()->json(['error' => 'Server misconfiguration'], 500);
        }

        MercadoPagoConfig::setAccessToken($mpToken);

        // Mercado Pago envía notificaciones con 'topic' o 'type' (según versión).
        $payload = $request->all();
        \Log::info('Webhook de Mercado Pago recibido:', $payload);

        try {
            $topic = $payload['topic'] ?? $payload['type'] ?? null;
            $id = $payload['id'] ?? $payload['data']['id'] ?? null;

            if (empty($topic) || empty($id)) {
                \Log::warning('Webhook MercadoPago sin topic/id válido.', $payload);
                return response()->json(['status' => 'ignored'], 200);
            }

            // Solo procesamos pagos por ahora
            if (strtolower($topic) !== 'payment' && strtolower($topic) !== 'payment_created' && strtolower($topic) !== 'merchant_order') {
                \Log::info('Webhook MercadoPago topic no soportado: '.$topic);
                return response()->json(['status' => 'ignored'], 200);
            }

            // Usar PaymentClient para obtener el detalle del pago
            $paymentClient = new PaymentClient();
            try {
                // Se usa try-catch para manejar errores de SDK (ej. payment_id inexistente)
                $paymentResource = $paymentClient->get((string) $id);
            } catch (\Exception $e) {
                \Log::error('Error obteniendo payment desde SDK: '.$e->getMessage());
                return response()->json(['status' => 'not_found'], 200);
            }

            // Convertir recurso a arreglo para manejo uniforme
            $paymentData = method_exists($paymentResource, 'toArray') ? $paymentResource->toArray() : json_decode(json_encode($paymentResource), true);

            $status = $paymentData['status'] ?? $paymentData['status_detail'] ?? null;
            $reference = $paymentData['external_reference'] ?? ($paymentData['metadata']['external_reference'] ?? null);
            $paymentId = $paymentData['id'] ?? null;
            $total = $paymentData['transaction_details']['total_paid_amount'] ?? ($paymentData['transaction_amount'] ?? ($paymentData['transaction_amount'] ?? null));

            // Si no hay referencia externa, no podemos relacionar con Reserva
            if (empty($reference)) {
                \Log::warning('Payment sin external_reference: '.$paymentId, $paymentData);
                return response()->json(['status' => 'no_reference'], 200);
            }

            // La referencia la guardamos como JSON cuando se creó la preferencia
            $external = json_decode($reference, true);
            if (!is_array($external)) {
                \Log::warning('external_reference no es JSON válido: '.$reference);
                return response()->json(['status' => 'invalid_reference'], 200);
            }

            // Solo actuamos si el pago está aprobado
            if (strtolower($status) === 'approved' || strtolower($status) === 'authorized') {
                // Evitar duplicados: buscar por referencia de pago en la tabla reservas
                $existing = \App\Models\Reserva::where('referencia_pago', $paymentId)->first();
                if ($existing) {
                    \Log::info('Reserva ya procesada para payment id: '.$paymentId);
                    return response()->json(['status' => 'already_processed'], 200);
                }

                // Crear reserva con los campos necesarios
                $reserva = \App\Models\Reserva::create([
                    'user_id' => $external['user_id'] ?? null,
                    'turno_id' => $external['turno_id'] ?? null,
                    'nombre_jugador' => $external['nombre_jugador'] ?? ($external['nombre'] ?? null),
                    'whatsapp' => $external['whatsapp'] ?? null,
                    'cantidad_jugadores' => $external['cantidad_jugadores'] ?? null,
                    'necesita_paleta' => $external['necesita_paleta'] ?? false,
                    'buscar_pareja' => $external['buscar_pareja'] ?? false,
                    'estado' => 'pagado',
                    'referencia_pago' => $paymentId,
                    'precio_total' => $total,
                ]);

                // Si existe el turno, marcarlo como reservado
                if (!empty($external['turno_id'])) {
                    $turno = \App\Models\Turno::find($external['turno_id']);
                    if ($turno) {
                        $turno->estado = 'reservado';
                        $turno->save();
                    }
                }

                \Log::info('Reserva creada desde webhook MercadoPago, id_reserva: '.($reserva->id ?? 'n/a'));

                return response()->json(['status' => 'reservation_created', 'reserva_id' => $reserva->id ?? null], 200);
            }

            \Log::info('Payment no aprobado o estado distinto a approved: '.$status, $paymentData);
            return response()->json(['status' => 'not_approved'], 200);

        } catch (\Throwable $e) {
            $extra = ['payload' => $payload, 'trace' => $e->getTraceAsString()];
            if (method_exists($e, 'getResponse') && ($resp = $e->getResponse())) {
                try {
                    $body = method_exists($resp, 'getBody') ? (string) $resp->getBody() : (string) $resp;
                    $extra['mp_response_body'] = $body;
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $extra['mp_response_json'] = $decoded;
                    }
                } catch (\Throwable $_) {
                    $extra['mp_response_error'] = 'failed_to_read_response_body';
                }
            }
            \Log::error('Error procesando webhook MercadoPago: ' . $e->getMessage(), $extra);
            return response()->json(['error' => 'processing_error'], 500);
        }
    }

 
    private function createPreferenceViaHttp(array $requestBody, ?string $mpToken): ?array
    {
        if (empty($mpToken)) {
            throw new \RuntimeException('MercadoPago token missing for HTTP fallback');
        }

        $response = Http::withToken($mpToken)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', $requestBody);

        $body = $response->body();
        $decoded = null;
        try {
            $decoded = json_decode($body, true);
        } catch (\Throwable $_) {
            $decoded = null;
        }

        // Log the raw response for debugging
        \Log::info('MercadoPago HTTP fallback response', ['status' => $response->status(), 'body' => $body]);

        if ($response->successful() && is_array($decoded)) {
            return $decoded;
        }

        // If Mercado Pago returned a JSON error body, return it so caller can inspect
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Support\Facades\Http;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\Precio;

class MercadoPagoController extends Controller
{
    /**
     * Crear una preferencia de pago en Mercado Pago.
     */
    public function crearPreferencia(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'required|string',
            'turno_id' => 'required|exists:turnos,id',
            'whatsapp' => 'required|string|max:15',
            'cantidad_jugadores' => 'required|in:2,4',
            'buscar_pareja' => 'required|boolean',
            'necesita_paleta' => 'required|boolean',
        ]);

        $turno = Turno::find($request->turno_id);
        $precioCancha = Precio::paraCancha($turno->cancha);

        if ($precioCancha->requiere_4_jugadores && (int) $request->cantidad_jugadores !== 4) {
            return response()->json([
                'error' => "La cancha {$turno->cancha} solo admite reservas con 4 jugadores.",
            ], 422);
        }

        try {
            $mpToken = config('services.mercadopago.token', env('MP_ACCESS_TOKEN'));
            if (!$mpToken) {
                return response()->json(['error' => 'Token de Mercado Pago no configurado'], 500);
            }

            MercadoPagoConfig::setAccessToken($mpToken);
            $client = new PreferenceClient();

            $backendUrl = config('app.url');
            $frontendUrl = config('app.frontend_url');
            $nombreJugador = auth()->check() ? auth()->user()->name : 'Invitado';

            if (!$backendUrl || !$frontendUrl) {
                throw new \Exception("APP_URL o FRONTEND_URL no configuradas.");
            }

            $requestBody = [
                'items' => [
                    [
                        'title' => $request->descripcion,
                        'quantity' => 1,
                        'unit_price' => (float) $request->monto,
                    ]
                ],
                'back_urls' => [
                    'success' => 'https://kakopadel.vercel.app/pago/success',
                    'failure' => 'https://kakopadel.vercel.app/failure',
                    'pending' => 'https://kakopadel.vercel.app/pending',
                ],
                'notification_url' => 'https://kako-backend.onrender.com/api/mercadopago/webhook',

                'external_reference' => json_encode([
                    'turno_id' => $request->turno_id,
                    'cantidad_jugadores' => $request->cantidad_jugadores,
                    'buscar_pareja' => $request->buscar_pareja,
                    'whatsapp' => $request->whatsapp,
                    'necesita_paleta' => $request->necesita_paleta,
                    'user_id' => auth()->id(),
                    'nombre_jugador' => $nombreJugador,
                ]),
            ];

            $preference = $client->create($requestBody);

            return response()->json(['id' => $preference->id], 200);

        } catch (\Throwable $e) {
            \Log::error('Error creando preferencia MercadoPago: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'No se pudo generar la preferencia de pago.',
                'details' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Manejar webhook de Mercado Pago.
     */
    public function handleWebhook(Request $request)
    {
        if (!$this->validarFirmaWebhook($request)) {
            \Log::warning('Webhook de MercadoPago rechazado: firma inválida o ausente.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Firma inválida'], 401);
        }

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

    /**
     * Valida que el webhook realmente venga de Mercado Pago, verificando el
     * header x-signature (HMAC-SHA256) contra la clave secreta del webhook.
     * https://www.mercadopago.com.ar/developers/es/docs/checkout-api/additional-content/your-integrations/notifications/webhooks#editor_2
     */
    private function validarFirmaWebhook(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        if (!$secret) {
            \Log::error('MP_WEBHOOK_SECRET no configurado; se rechaza el webhook por seguridad.');
            return false;
        }

        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');

        // PHP reemplaza los puntos por guiones bajos en las claves de la query
        // string (data.id -> data_id) antes de que Laravel la vea, así que
        // "data.id" nunca se puede leer con $request->query('data.id').
        $dataId = $request->query('data_id') ?? $request->query('id');

        if (!$xSignature || !$xRequestId || !$dataId) {
            return false;
        }

        $ts = null;
        $hashRecibido = null;

        foreach (explode(',', $xSignature) as $parte) {
            $par = explode('=', trim($parte), 2);
            if (count($par) !== 2) {
                continue;
            }
            [$clave, $valor] = $par;
            if (trim($clave) === 'ts') {
                $ts = trim($valor);
            } elseif (trim($clave) === 'v1') {
                $hashRecibido = trim($valor);
            }
        }

        if (!$ts || !$hashRecibido) {
            return false;
        }

        $dataIdNormalizado = strtolower((string) $dataId);
        $manifest = "id:{$dataIdNormalizado};request-id:{$xRequestId};ts:{$ts};";
        $hashCalculado = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($hashCalculado, $hashRecibido);
    }
}
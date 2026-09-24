<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Admin\TurnoAdminController;
use App\Http\Controllers\Admin\ReservaAdminController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MercadoPagoController;

// -----------------------------
// RUTAS PÚBLICAS
// -----------------------------
Route::get('/turnos', [TurnoController::class, 'show']); 
Route::get('/precios-publicos', [TurnoController::class, 'getPrecios']);
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::post('/auth/google', [\App\Http\Controllers\AuthController::class, 'loginWithGoogle']);
Route::post('/mercadopago/webhook', [MercadoPagoController::class, 'handleWebhook']);
Route::post('/mercadopago/preference-debug', [MercadoPagoController::class, 'crearPreferencia']);

// -----------------------------
// RUTAS USUARIOS AUTENTICADOS
// -----------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [\App\Http\Controllers\Api\UserController::class, 'mydata']);
    Route::get('/mis-reservas/ultima', [ReservaController::class, 'ultimaReservaUsuario']);
    Route::post('/reservas', [ReservaController::class, 'store']);           
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);        
    Route::delete('/reservas/{id}', [ReservaController::class, 'cancelar']); 
    Route::get('/reservas/mias', [ReservaController::class, 'mias']);     
    Route::post('/mercadopago/preference', [MercadoPagoController::class, 'crearPreferencia']);
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    Route::put('/me/password', [\App\Http\Controllers\AuthController::class, 'changePassword']);
});

// -----------------------------
// RUTAS ADMIN
// -----------------------------
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])
    ->prefix('admin')
    ->group(function () {
        Route::get('turnos', [TurnoAdminController::class, 'index']);     
        Route::post('turnos', [TurnoAdminController::class, 'store']);       
        Route::put('turnos/{id}', [TurnoAdminController::class, 'update']);  
        Route::get('reservas', [ReservaAdminController::class, 'index']);
        Route::get('reservas/hoy', [ReservaAdminController::class, 'hoy']);
        Route::get('reservas/{id}', [ReservaAdminController::class, 'show']);    
        Route::put('reservas/{id}', [ReservaAdminController::class, 'update']);  
        Route::post('/turnos/{id}/retrasar', [TurnoAdminController::class, 'retrasarTurnos']);
        Route::post('/turnos/{id}/adelantar', [TurnoAdminController::class, 'adelantarTurnos']);
        Route::get('precios', [TurnoAdminController::class, 'precios']);
        Route::put('/turnos/precio/{cancha}', [TurnoAdminController::class, 'updatePrecioPorCancha']);
        Route::put('reservas/{id}/liberar', [ReservaAdminController::class, 'liberar']);         
        Route::get('usuarios', [UserController::class, 'index']);
        Route::get('usuarios/{id}', [UserController::class, 'show']);
        Route::put('usuarios/{id}', [UserController::class, 'update']);
        Route::delete('usuarios/{id}', [UserController::class, 'destroy']);
    });

// -----------------------------
// RUTA PARA GENERAR, EDITAR Y BORRAR TURNOS (USO INTERNO, PROTEGIDA POR TOKEN)
// -----------------------------
$verificarTokenCron = function (string $token) {
    $secreto = env('CRON_SECRET', '');
    return $secreto !== '' && hash_equals($secreto, $token);
};

$ejecutarComandoCron = function (string $comando, string $mensajeExito) {
    try {
        // El cliente (cron-job.org) puede cortar la conexión por timeout
        // antes de que el comando termine; que siga corriendo igual.
        ignore_user_abort(true);
        set_time_limit(300);
        Artisan::call($comando);

        return response()->json([
            'status' => 'success',
            'message' => $mensajeExito,
        ]);
    } catch (\Exception $e) {
        Log::error("Error en cron [{$comando}]: " . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'details' => $e->getMessage(),
        ], 500);
    }
};

Route::get('/generar-turnos-sistema/{token}', function ($token) use ($verificarTokenCron, $ejecutarComandoCron) {
    if (! $verificarTokenCron($token)) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    return $ejecutarComandoCron('turnos:generar-mensual', 'Turnos procesados correctamente');
});

Route::get('/marcar-inactivos-sistema/{token}', function ($token) use ($verificarTokenCron, $ejecutarComandoCron) {
    if (! $verificarTokenCron($token)) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    return $ejecutarComandoCron('turnos:marcar-inactivos', 'Turnos inactivos actualizados');
});

Route::get('/limpiar-turnos-sistema/{token}', function ($token) use ($verificarTokenCron, $ejecutarComandoCron) {
    if (! $verificarTokenCron($token)) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    return $ejecutarComandoCron('turnos:limpiar-viejos', 'Turnos viejos eliminados');
});
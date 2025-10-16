<?php

use Illuminate\Support\Facades\Route;
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
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);
// WEBHOOK MERCADOPAGO (DEBE SER PÚBLICA)
Route::post('/mercadopago/webhook', [MercadoPagoController::class, 'handleWebhook']);
// TEMP: public debug endpoint to test preference creation without auth (remove after debugging)
Route::post('/mercadopago/preference-debug', [MercadoPagoController::class, 'crearPreferencia']);

// -----------------------------
// RUTAS USUARIOS AUTENTICADOS
// -----------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Usuarios
    Route::get('/me', [\App\Http\Controllers\Api\UserController::class, 'mydata']);

    // Reservas
    Route::post('/reservas', [ReservaController::class, 'store']);          
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);       
    Route::delete('/reservas/{id}', [ReservaController::class, 'cancelar']); 
    Route::get('/reservas/mias', [ReservaController::class, 'mias']);       
    
    //Pagos
    Route::post('/mercadopago/preference', [MercadoPagoController::class, 'crearPreferencia']);
    // Logout
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
});

// -----------------------------
// RUTAS ADMIN
// -----------------------------
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])
    ->prefix('admin')
    ->group(function () {
        // Turnos
        Route::get('turnos', [TurnoAdminController::class, 'index']);     
        Route::post('turnos', [TurnoAdminController::class, 'store']);       
        Route::put('turnos/{id}', [TurnoAdminController::class, 'update']);  

        // Reservas
        Route::get('reservas', [ReservaAdminController::class, 'index']);
        Route::get('reservas/hoy', [ReservaAdminController::class, 'hoy']);
        Route::get('reservas/{id}', [ReservaAdminController::class, 'show']);    
        Route::put('reservas/{id}', [ReservaAdminController::class, 'update']);  
        Route::get('precios', [TurnoAdminController::class, 'precios']);
        Route::put('/turnos/precio/{cancha}', [TurnoAdminController::class, 'updatePrecioPorCancha']);
        Route::put('reservas/{id}/liberar', [ReservaAdminController::class, 'liberar']); 

        
        // Usuarios
        Route::get('usuarios', [UserController::class, 'index']);
        Route::get('usuarios/{id}', [UserController::class, 'show']);
        Route::put('usuarios/{id}', [UserController::class, 'update']);
        Route::delete('usuarios/{id}', [UserController::class, 'destroy']);

        //Rutas innecesarias temporalmente
        /*
        Route::get('turnos/{id}', [TurnoAdminController::class, 'show']);
        Route::delete('turnos/{id}', [TurnoAdminController::class, 'destroy']); 
        Route::delete('reservas/{id}', [ReservaAdminController::class, 'destroy']); 
        */

    });

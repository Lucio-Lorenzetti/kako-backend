<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Admin\TurnoAdminController;
use App\Http\Controllers\Admin\ReservaAdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Aquí definimos las rutas de la API que el frontend (React) va a consumir.
| Están separadas en:
| - Públicas: accesibles a cualquier usuario (ver turnos, hacer reservas).
| - Protegidas (Admin): solo accesibles por el dueño con rol admin.
|--------------------------------------------------------------------------
*/

// -----------------------------
// 🔹 RUTAS PÚBLICAS (usuarios)
// -----------------------------
Route::get('/turnos', [TurnoController::class, 'index']);          // Ver turnos disponibles
Route::post('/reservas', [ReservaController::class, 'store']);     // Crear reserva
Route::get('/reservas/{id}', [ReservaController::class, 'show']);  // Ver una reserva

// -----------------------------
// 🔹 RUTAS PROTEGIDAS (ADMIN)
// -----------------------------
Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {

    // Turnos
    Route::get('turnos', [TurnoAdminController::class, 'index']);        // Listar todos los turnos
    Route::get('turnos/{id}', [TurnoAdminController::class, 'show']);    // Ver detalle de un turno
    Route::post('turnos', [TurnoAdminController::class, 'store']);       // Crear nuevo turno
    Route::put('turnos/{id}', [TurnoAdminController::class, 'update']);  // Editar turno
    Route::delete('turnos/{id}', [TurnoAdminController::class, 'destroy']); // Eliminar turno

    // Reservas
    Route::get('reservas', [ReservaAdminController::class, 'index']);        // Listar todas las reservas
    Route::get('reservas/{id}', [ReservaAdminController::class, 'show']);    // Ver detalle de una reserva
    Route::put('reservas/{id}', [ReservaAdminController::class, 'update']);  // Cambiar estado de reserva
    Route::put('reservas/{id}/liberar', [ReservaAdminController::class, 'liberar']); // Liberar reserva
    Route::delete('reservas/{id}', [ReservaAdminController::class, 'destroy']); // Eliminar reserva
});

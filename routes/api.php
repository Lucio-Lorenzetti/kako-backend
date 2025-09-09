use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Admin\TurnoAdminController;
use App\Http\Controllers\Admin\ReservaAdminController;
use App\Http\Controllers\Api\PagoController;

// -----------------------------
// 🔹 RUTAS PÚBLICAS
// -----------------------------
Route::get('/turnos/disponibles', [TurnoController::class, 'disponibles']); 
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

// -----------------------------
// 🔹 RUTAS USUARIOS AUTENTICADOS
// -----------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Reservas
    Route::post('/reservas', [ReservaController::class, 'store']);          
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);       
    Route::delete('/reservas/{id}', [ReservaController::class, 'cancelar']); 
    Route::get('/reservas/mias', [ReservaController::class, 'mias']);       

    // Pagos
    Route::post('/reservas/{id}/pagar', [PagoController::class, 'iniciarPago']); 

    // Logout
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
});

// -----------------------------
// 🔹 WEBHOOK MERCADOPAGO
// -----------------------------
Route::post('/webhook/mercadopago', [PagoController::class, 'webhook']);

// -----------------------------
// 🔹 RUTAS ADMIN
// -----------------------------
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])
    ->prefix('admin')
    ->group(function () {
        // Turnos
        Route::get('turnos', [TurnoAdminController::class, 'index']);        
        Route::get('turnos/{id}', [TurnoAdminController::class, 'show']);    
        Route::post('turnos', [TurnoAdminController::class, 'store']);       
        Route::put('turnos/{id}', [TurnoAdminController::class, 'update']);  
        Route::delete('turnos/{id}', [TurnoAdminController::class, 'destroy']); 

        // Reservas
        Route::get('reservas', [ReservaAdminController::class, 'index']);        
        Route::get('reservas/{id}', [ReservaAdminController::class, 'show']);    
        Route::put('reservas/{id}', [ReservaAdminController::class, 'update']);  
        Route::put('reservas/{id}/liberar', [ReservaAdminController::class, 'liberar']); 
        Route::delete('reservas/{id}', [ReservaAdminController::class, 'destroy']); 
    });

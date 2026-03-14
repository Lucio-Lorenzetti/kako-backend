<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;

Route::get('/', function () {
    return view('welcome');
});

// Si entran a una ruta de pago, mándalos al puerto del frontend
Route::get('/pago/{status}', function ($status) {
    // Redirigir al puerto 5173 donde corre Vite
    return Redirect::to('http://localhost:5173/pago/' . $status . '?' . http_build_query(request()->all()));
});
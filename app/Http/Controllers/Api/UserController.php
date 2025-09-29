<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // Obtener los datos del usuario autenticado
    public function mydata(Request $request)
    {
        return response()->json($request->user());
    }

    // Listar todos los usuarios (ordenados por creación)
    public function index()
    {
        return User::orderBy('created_at', 'desc')->get();
    }

    // Ver detalle de un usuario
    public function show($id)
    {
        return User::findOrFail($id);
    }

    // Actualizar rol o estado de un usuario
    public function update(Request $request, $id)
    {
        // Validación: solo los valores permitidos
        $request->validate([
            'role'   => 'sometimes|in:user,admin',
            'estado' => 'sometimes|in:Activo,Inactivo',
        ]);

        $usuario = User::findOrFail($id);

        // Actualizar solo los campos enviados
        $usuario->update($request->only(['role', 'estado']));

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }

    // Eliminar un usuario
    public function destroy($id)
    {
        $usuario = User::findOrFail($id);
        $usuario->delete();

        return response()->json(null, 204);
    }
}

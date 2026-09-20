<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    // Obtener los datos del usuario autenticado
    public function mydata(Request $request)
    {
        return response()->json($request->user());
    }

    // Listar todos los usuarios (ordenados por creación)
    public function index(Request $request)
    {
        $query = User::orderBy('created_at', 'desc');

        // Los developer son invisibles para los admins comunes
        if ($request->user()->role !== 'developer') {
            $query->where('role', '!=', 'developer');
        }

        return $query->get();
    }

    // Ver detalle de un usuario
    public function show(Request $request, $id)
    {
        $usuario = User::findOrFail($id);

        if ($usuario->role === 'developer' && $request->user()->role !== 'developer') {
            abort(404);
        }

        return $usuario;
    }

    // Actualizar rol, estado o contraseña de un usuario
    public function update(Request $request, $id)
    {
        $actor = $request->user();
        $usuario = User::findOrFail($id);

        // Un admin común no puede ni ver ni tocar a un developer
        if ($usuario->role === 'developer' && $actor->role !== 'developer') {
            abort(404);
        }

        $esDeveloper = $actor->role === 'developer';
        $rolesPermitidos = $esDeveloper ? 'user,admin,developer' : 'user,admin';

        $request->validate([
            'role'     => "sometimes|in:{$rolesPermitidos}",
            'estado'   => 'sometimes|in:Activo,Inactivo',
            'password' => 'sometimes|string|min:8',
        ]);

        // Gestionar administradores (crear/degradar) y cambiar contraseñas son
        // permisos exclusivos del developer, por encima de lo que puede hacer un admin
        if (!$esDeveloper) {
            if ($request->filled('password')) {
                return response()->json(['error' => 'No autorizado para cambiar contraseñas'], 403);
            }
            if ($request->filled('role') && ($request->role === 'admin' || $usuario->role === 'admin')) {
                return response()->json(['error' => 'No autorizado para gestionar administradores'], 403);
            }
        }

        $data = $request->only(['role', 'estado']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $usuario->update($data);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }

    // Eliminar un usuario
    public function destroy(Request $request, $id)
    {
        $actor = $request->user();
        $usuario = User::findOrFail($id);

        // Un admin común no puede eliminar developers ni otros admins
        if ($usuario->role === 'developer') {
            abort(404);
        }
        if ($usuario->role === 'admin' && $actor->role !== 'developer') {
            return response()->json(['error' => 'No autorizado para eliminar administradores'], 403);
        }

        $usuario->delete();

        return response()->json(null, 204);
    }
}

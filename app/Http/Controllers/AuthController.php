<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Registro
    public function register(Request $request)
    {
        // Determinar si quien está haciendo el request es un admin logueado
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';

        // Validaciones
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed', // requiere password_confirmation
        ];

        // Solo validar el role si lo está poniendo un admin
        if ($isAdmin) {
            $rules['role'] = 'required|in:user,admin';
            $rules['estado'] = 'required|in:Activo,Inactivo';
        }

        $request->validate($rules);

        // Asignar rol y estado
        $role = $isAdmin ? $request->role : 'user';
        $estado = $isAdmin ? $request->estado : 'Activo';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'estado' => $estado,
        ]);

        // Crear token para login
        $token = $user->createToken('token-login')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('token-login')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        // 🔹 Eliminar TODOS los tokens del usuario (logout global)
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout exitoso, todas las sesiones cerradas']);
    }
}

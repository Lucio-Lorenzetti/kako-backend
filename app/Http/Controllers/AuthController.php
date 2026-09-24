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
        // Determinar si quien está haciendo el request es staff logueado (admin o developer)
        $actor = auth()->check() ? auth()->user() : null;
        $isDeveloper = $actor && $actor->role === 'developer';
        $isStaff = $actor && in_array($actor->role, ['admin', 'developer']);

        // Validaciones
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed', // requiere password_confirmation
        ];

        // Solo validar el role si lo está poniendo un miembro del staff
        // Crear admins (o developers) es exclusivo del developer; un admin común solo puede crear usuarios normales
        if ($isStaff) {
            $rolesPermitidos = $isDeveloper ? 'user,admin,developer' : 'user';
            $rules['role'] = "required|in:{$rolesPermitidos}";
            $rules['estado'] = 'required|in:Activo,Inactivo';
        }

        $request->validate($rules);

        // Asignar rol y estado
        $role = $isStaff ? $request->role : 'user';
        $estado = $isStaff ? $request->estado : 'Activo';

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

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('token-login')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    // Login / registro automático con Google (recibe el id_token que genera el botón de Google en el front)
    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $clientId = config('services.google.client_id');
        if (!$clientId) {
            return response()->json(['error' => 'Google login no configurado'], 500);
        }

        $client = new \Google\Client(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($request->id_token);

        if (!$payload || empty($payload['email']) || empty($payload['email_verified']) || empty($payload['sub'])) {
            return response()->json(['error' => 'Token de Google inválido'], 401);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'] ?? $email;

        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if ($user) {
            if (!$user->google_id) {
                $user->update(['google_id' => $googleId]);
            }
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'google_id' => $googleId,
                'role' => 'user',
                'estado' => 'Activo',
            ]);
        }

        $token = $user->createToken('token-login')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        // 🔹 Eliminar TODOS los tokens del usuario (logout global)
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout exitoso, todas las sesiones cerradas']);
    }

    // Cambiar la propia contraseña estando logueado
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed', // requiere password_confirmation
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }
}

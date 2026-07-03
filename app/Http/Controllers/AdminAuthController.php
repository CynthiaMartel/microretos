<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UpdatePerfilRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|max:128',
        ]);

        // Bloqueo por intentos fallidos: clave = email + IP (resiste rotación de IPs y de emails)
        $throttleKey = 'login.' . Str::lower($request->input('email')) . '.' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $minutos = ceil(RateLimiter::availableIn($throttleKey) / 60);
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Inténtalo en ' . $minutos . ' minuto(s).',
            ], 429);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 900); // ventana de 15 minutos
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta está bloqueada. Contacta con el administrador.',
            ], 403);
        }

        if (!$user->isSuperAdmin() && $user->email_verified_at === null) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta aún no ha sido activada. Contacta con el administrador.',
            ], 403);
        }

        // Limitar a 3 tokens concurrentes (móvil + tablet + escritorio): borrar los más antiguos
        $count = $user->tokens()->count();
        if ($count >= 3) {
            $user->tokens()->orderBy('created_at')->limit($count - 2)->delete();
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success'             => true,
            'token'               => $token,
            'role'                => $user->role,
            'name'                => $user->name,
            'centro_educativo_id' => $user->centro_educativo_id,
            'centro_nombre'       => $user->centroEducativo?->nombre,
            'centro_img'          => $user->centroEducativo?->img,
            'message'             => 'Acceso concedido.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada.',
        ]);
    }

    public function verifyPassword(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Contraseña incorrecta.'], 401);
        }

        return response()->json(['success' => true]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revocar el token actual y emitir uno nuevo (rota la sesión sin pedir contraseña)
        $user->currentAccessToken()->delete();
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'role'    => $user->role,
        ]);
    }

    public function getPerfil(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    public function updatePerfil(UpdatePerfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->name = $data['name'];

        $passwordChanged = !empty($data['password']);

        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->save();

        if ($passwordChanged) {
            // Revocar todos los tokens en todos los dispositivos
            $user->tokens()->delete();

            return response()->json([
                'success'          => true,
                'password_changed' => true,
                'message'          => 'Contraseña actualizada. Por seguridad, vuelve a iniciar sesión.',
            ]);
        }

        return response()->json([
            'success'          => true,
            'password_changed' => false,
            'message'          => 'Perfil actualizado correctamente.',
            'data'             => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
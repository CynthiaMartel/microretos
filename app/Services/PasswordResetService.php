<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    // Solo se envía a cuentas que podrían iniciar sesión (activadas y no bloqueadas).
    // El resultado nunca se devuelve al cliente: la respuesta es siempre la misma
    // para no revelar qué emails tienen cuenta.
    public function enviarEnlace(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user || !$user->isOperational()) {
            return;
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            Log::info('Enlace de restablecimiento de contraseña enviado', ['user_id' => $user->id]);
        }
    }

    // true si el token era válido y la contraseña se ha cambiado
    /** @param array<string, mixed> $data */
    public function restablecer(array $data): bool
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !$user->isOperational()) {
            return false;
        }

        $status = Password::reset(
            [
                'email'                 => $data['email'],
                'password'              => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token'                 => $data['token'],
            ],
            function (User $user, string $password) {
                // Cast 'hashed' del modelo; el hook saving rota remember_token ("Recordarme")
                $user->password            = $password;
                $user->password_changed_at = now();
                $user->save();

                // Fuera de todos los dispositivos: sesiones web y tokens Sanctum
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->tokens()->delete();

                event(new PasswordReset($user));
                Log::info('Contraseña restablecida mediante enlace', ['user_id' => $user->id]);
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}

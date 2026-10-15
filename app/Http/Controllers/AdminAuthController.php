<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdatePerfilRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $email = Str::lower($data['email']);

        // Bloqueo por intentos fallidos: clave = email + IP (resiste rotación de IPs y de emails)
        $throttleKey = 'login.' . $email . '.' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $minutos = ceil(RateLimiter::availableIn($throttleKey) / 60);
            Log::warning('Login bloqueado por rate limit', ['email' => $this->maskEmail($email), 'ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Inténtalo en ' . $minutos . ' minuto(s).',
            ], 429);
        }

        // Sin "Recordarme" aquí: la cookie se emite más abajo, solo si la cuenta
        // está operativa y su rol lo permite
        if (!Auth::attempt(['email' => $email, 'password' => $data['password']])) {
            RateLimiter::hit($throttleKey, 900); // ventana de 15 minutos
            // Nunca la contraseña en sí (PII/secreto) — solo su longitud, para poder distinguir
            // "se ha escrito otra contraseña" de "la cuenta cambió" sin loguear el valor real.
            Log::warning('Login fallido: credenciales incorrectas', [
                'email'            => $this->maskEmail($email),
                'ip'               => $request->ip(),
                'password_length'  => strlen((string) $data['password']),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            Log::warning('Login denegado: cuenta bloqueada', ['user_id' => $user->id, 'ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta está bloqueada. Contacta con el administrador.',
            ], 403);
        }

        if (!$user->isSuperAdmin() && $user->email_verified_at === null) {
            Auth::logout();
            Log::warning('Login denegado: cuenta no activada', ['user_id' => $user->id, 'ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta aún no ha sido activada. Contacta con el administrador.',
            ], 403);
        }

        $remember = (bool) ($data['remember'] ?? false) && $user->puedeRecordarSesion();
        if ($remember) {
            Auth::guard('web')->login($user, true);
        }

        $request->session()->regenerate();

        // Limitar a 3 dispositivos concurrentes (móvil + tablet + escritorio): podar
        // las sesiones más antiguas de este usuario, dejando la recién creada. La fila
        // de ESTA sesión todavía no existe en `sessions` en este punto del ciclo de vida
        // del request (Laravel la persiste al final, en StartSession) — por eso se cuenta
        // +1 (la propia) en vez de esperar a que aparezca, o el recuento iría siempre
        // un login por detrás.
        $totalConEstaSesion = DB::table('sessions')->where('user_id', $user->id)->count() + 1;
        if ($totalConEstaSesion > 3) {
            $idsAntiguos = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity')
                ->limit($totalConEstaSesion - 3)
                ->pluck('id');
            DB::table('sessions')->whereIn('id', $idsAntiguos)->delete();
        }

        Log::info('Login correcto', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json([
            'success'             => true,
            'role'                => $user->role,
            'name'                => $user->name,
            'centro_educativo_id' => $user->centro_educativo_id,
            'centro_nombre'       => $user->centroEducativo?->nombre,
            'centro_img'          => $user->centroEducativo?->img,
            'minutos_restantes'   => $this->minutosRestantesSesion($request),
            'recordado'           => $remember,
            'message'             => 'Acceso concedido.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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

    public function getPerfil(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'role'                => $user->role,
                'centro_educativo_id' => $user->centro_educativo_id,
                'centro_nombre'       => $user->centroEducativo?->nombre,
                'centro_img'          => $user->centroEducativo?->img,
                'minutos_restantes'   => $this->minutosRestantesSesion($request),
                'recordado'           => $this->sesionRecordada($request),
            ],
        ]);
    }

    // "Recordarme": el navegador conserva la cookie recaller (HttpOnly, JS no puede
    // verla) — el frontend lo necesita saber para no cerrar sesión por inactividad.
    private function sesionRecordada(Request $request): bool
    {
        return $request->cookies->has(Auth::guard('web')->getRecallerName());
    }

    // Sesión de Laravel (driver 'database'): expira 'session.lifetime' minutos después
    // del último request que la tocó (last_activity), no en un instante fijo — por eso
    // se recalcula en cada /perfil en vez de derivarse de un timestamp de login.
    private function minutosRestantesSesion(Request $request): int
    {
        $lastActivity = DB::table('sessions')
            ->where('id', $request->session()->getId())
            ->value('last_activity');

        if (!$lastActivity) {
            return (int) config('session.lifetime');
        }

        $transcurridos = (time() - (int) $lastActivity) / 60;

        return max(0, (int) floor(config('session.lifetime') - $transcurridos));
    }

    public function updatePerfil(UpdatePerfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->name = $data['name'];

        $passwordChanged = !empty($data['password']);

        if ($passwordChanged) {
            $user->password = $data['password'];
            $user->password_changed_at = now();
        }

        $user->save();

        if ($passwordChanged) {
            // Cerrar la sesión en el resto de dispositivos (nunca la propia: Laravel
            // volvería a persistirla al final de esta misma request, dejando al usuario
            // con la falsa impresión de haber cerrado sesión en su dispositivo actual).
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();

            Log::info('Contraseña cambiada por el propio usuario', ['user_id' => $user->id]);

            return response()->json([
                'success'          => true,
                'password_changed' => true,
                'message'          => 'Contraseña actualizada. Se ha cerrado la sesión en tus otros dispositivos.',
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

    // Nunca loguear el email completo (PII) — solo u***@dominio.com
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return ($local === '' ? '' : $local[0] . '***') . '@' . $domain;
    }
}
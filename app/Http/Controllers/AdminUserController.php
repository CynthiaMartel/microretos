<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $auth = $request->user();

        $query = User::with('centroEducativo')
            ->whereNot('id', $auth->id)
            ->orderByDesc('created_at');

        if ($auth->isAdmin()) {
            // Admin de centro: solo ve docentes de su propio centro
            $query->where('centro_educativo_id', $auth->centro_educativo_id)
                  ->whereNotIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN]);
        }

        return response()->json(['data' => $this->formatUsers($query->get())]);
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $request->user();

        if ($auth->isAdmin()) {
            // Admin de centro: solo puede crear docentes, asignados automáticamente a su centro
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:254|unique:users,email',
                'password' => ['required', 'string', 'max:128', Password::min(8)->mixedCase()->numbers()],
            ]);

            $user = User::create([
                'name'                => $data['name'],
                'email'               => $data['email'],
                'password'            => Hash::make($data['password']),
                'role'                => User::ROLE_DOCENTE,
                'is_blocked'          => false,
                'email_verified_at'   => null,
                'centro_educativo_id' => $auth->centro_educativo_id,
            ]);
        } else {
            // Superadmin: puede crear docente (2), empresa (3) o admin de centro (4)
            $data = $request->validate([
                'name'                => 'required|string|max:255',
                'email'               => 'required|email|max:254|unique:users,email',
                'password'            => ['required', 'string', 'max:128', Password::min(8)->mixedCase()->numbers()],
                'role'                => 'required|in:2,3,4',
                'centro_educativo_id' => 'nullable|exists:centro_educativo,id',
                'empresa_id'          => 'nullable|exists:empresas,id',
            ]);

            $user = User::create([
                'name'                => $data['name'],
                'email'               => $data['email'],
                'password'            => Hash::make($data['password']),
                'role'                => (int) $data['role'],
                'is_blocked'          => false,
                'email_verified_at'   => null,
                'centro_educativo_id' => $data['centro_educativo_id'] ?? null,
                'empresa_id'          => $data['empresa_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
            'message' => 'Cuenta creada. Actívala para que pueda iniciar sesión.',
        ], 201);
    }

    public function activar(Request $request, User $user): JsonResponse
    {
        if ($error = $this->checkScope($request->user(), $user)) return $error;

        $user->update(['email_verified_at' => now()]);

        return response()->json(['success' => true, 'data' => $this->formatUser($user->fresh())]);
    }

    public function toggleBloquear(Request $request, User $user): JsonResponse
    {
        if ($error = $this->checkScope($request->user(), $user)) return $error;

        $user->update(['is_blocked' => !$user->is_blocked]);

        if ($user->is_blocked) {
            $user->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $this->formatUser($user->fresh())]);
    }

    // Solo accesible por superadmin (middleware en ruta)
    public function asociarCentro(Request $request, User $user): JsonResponse
    {
        if (!$user->isDocente() && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Solo los docentes y administradores de centro pueden tener un centro asociado.'], 422);
        }

        $data = $request->validate([
            'centro_educativo_id' => 'nullable|exists:centro_educativo,id',
        ]);

        $user->update(['centro_educativo_id' => $data['centro_educativo_id']]);

        return response()->json(['success' => true, 'data' => $this->formatUser($user->fresh()->load('centroEducativo'))]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();

        if ($error = $this->checkScope($auth, $user)) return $error;

        // Admin de centro solo puede mantener el rol docente; superadmin puede asignar 2, 3 o 4
        $rolesPermitidos = $auth->isAdmin() ? '2' : '2,3,4';

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:254|unique:users,email,' . $user->id,
            'role'  => 'required|in:' . $rolesPermitidos,
        ];

        if ($auth->isSuperAdmin()) {
            $rules['centro_educativo_id'] = 'nullable|exists:centro_educativo,id';
            $rules['empresa_id']          = 'nullable|exists:empresas,id';
        }

        if ($request->filled('password')) {
            $rules['password'] = ['string', 'max:128', Password::min(8)->mixedCase()->numbers()];
        }

        $data = $request->validate($rules);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->role  = (int) $data['role'];

        if ($auth->isSuperAdmin() && array_key_exists('centro_educativo_id', $data)) {
            $user->centro_educativo_id = $data['centro_educativo_id'];
        }

        if ($auth->isSuperAdmin() && array_key_exists('empresa_id', $data)) {
            $user->empresa_id = $data['empresa_id'];
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['success' => true, 'data' => $this->formatUser($user->fresh()->load('centroEducativo'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($error = $this->checkScope($request->user(), $user)) return $error;

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['success' => true, 'message' => 'Cuenta enviada a la papelera.']);
    }

    public function papelera(Request $request): JsonResponse
    {
        $auth = $request->user();

        $query = User::onlyTrashed()
            ->with(['centroEducativo', 'empresa'])
            ->orderBy('deleted_at', 'desc');

        if ($auth->isAdmin()) {
            $query->where('centro_educativo_id', $auth->centro_educativo_id)
                  ->whereNotIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN]);
        }

        return response()->json(['data' => $this->formatUsers($query->get())]);
    }

    public function restaurar(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        $user = User::onlyTrashed()->findOrFail($id);

        if ($auth->isAdmin()) {
            if ($user->isSuperAdmin() || $user->isAdmin() ||
                $user->centro_educativo_id !== $auth->centro_educativo_id) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para restaurar este usuario.'], 403);
            }
        }

        $user->restore();

        return response()->json(['success' => true, 'data' => $this->formatUser($user->fresh())]);
    }

    public function destruir(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar una cuenta de superadministrador.'], 403);
        }

        if ($auth->isAdmin()) {
            if ($user->isAdmin() ||
                $user->centro_educativo_id !== $auth->centro_educativo_id) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para eliminar este usuario.'], 403);
            }
        }

        $user->tokens()->delete();
        $user->forceDelete();

        return response()->json(['success' => true, 'message' => 'Cuenta eliminada definitivamente.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Comprueba si $auth puede operar sobre $target.
     * Devuelve JsonResponse con 403 si no tiene permiso, null si sí.
     */
    private function checkScope(User $auth, User $target): ?JsonResponse
    {
        if ($target->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'No se puede modificar una cuenta de superadministrador.'], 403);
        }

        if ($auth->isAdmin()) {
            if ($target->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para modificar este usuario.'], 403);
            }
            if ($target->centro_educativo_id !== $auth->centro_educativo_id) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para modificar este usuario.'], 403);
            }
        }

        return null;
    }

    private function formatUsers($collection): array
    {
        return $collection->map(fn($u) => $this->formatUser($u))->values()->all();
    }

    private function formatUser(User $user): array
    {
        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'role'                => $user->role,
            'role_label'          => $user->roleName(),
            'is_blocked'          => $user->is_blocked,
            'is_active'           => $user->email_verified_at !== null,
            'centro_educativo_id' => $user->centro_educativo_id,
            'centro_nombre'       => $user->centroEducativo?->nombre,
            'centro_img'          => $user->centroEducativo?->img,
            'empresa_id'          => $user->empresa_id,
            'empresa_nombre'      => $user->empresa?->nombre_comercial,
            'created_at'          => $user->created_at?->toDateTimeString(),
            'deleted_at'          => $user->deleted_at?->toDateTimeString(),
        ];
    }
}

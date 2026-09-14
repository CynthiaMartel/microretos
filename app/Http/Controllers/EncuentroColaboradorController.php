<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncuentroColaboradorRequest;
use App\Http\Requests\UpdateEncuentroColaboradorRequest;
use App\Models\Encuentro;
use App\Models\User;
use Illuminate\Http\Request;

class EncuentroColaboradorController extends Controller
{
    /**
     * GET /api/encuentros/{id}/colaboradores
     */
    public function index(Request $request, $id)
    {
        $encuentro = $this->encuentroGestionablePor($request, $id);

        return response()->json(
            $encuentro->colaboradores()->get(['users.id', 'users.name'])->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'puede_editar' => (bool) $u->pivot->puede_editar,
            ])
        );
    }

    /**
     * GET /api/encuentros/{id}/colaboradores/candidatos
     * Docentes del mismo centro que el propietario, sin contar al propio propietario
     * ni a los que ya son colaboradores — para el selector del frontend.
     */
    public function candidatos(Request $request, $id)
    {
        $encuentro = $this->encuentroGestionablePor($request, $id);

        $centroId = $encuentro->docente?->centro_educativo_id;
        if (!$centroId) {
            return response()->json([]);
        }

        $yaColaboradores = $encuentro->colaboradores()->pluck('users.id');

        $docentes = User::where('centro_educativo_id', $centroId)
            ->whereIn('role', [User::ROLE_DOCENTE, User::ROLE_ADMIN])
            ->where('id', '!=', $encuentro->user_id)
            ->whereNotIn('id', $yaColaboradores)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($docentes);
    }

    /**
     * POST /api/encuentros/{id}/colaboradores
     */
    public function store(StoreEncuentroColaboradorRequest $request, $id)
    {
        $encuentro = $this->encuentroGestionablePor($request, $id);
        $validated = $request->validated();

        $encuentro->colaboradores()->syncWithoutDetaching([
            $validated['user_id'] => ['puede_editar' => $validated['puede_editar'] ?? false],
        ]);

        return response()->json(
            $encuentro->colaboradores()->get(['users.id', 'users.name'])->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'puede_editar' => (bool) $u->pivot->puede_editar,
            ]),
            201
        );
    }

    /**
     * PATCH /api/encuentros/{id}/colaboradores/{userId}
     */
    public function update(UpdateEncuentroColaboradorRequest $request, $id, $userId)
    {
        $encuentro = $this->encuentroGestionablePor($request, $id);
        $validated = $request->validated();

        $actualizado = $encuentro->colaboradores()->updateExistingPivot($userId, [
            'puede_editar' => $validated['puede_editar'],
        ]);

        if (!$actualizado) {
            return response()->json(['error' => 'Ese usuario no es colaborador de este encuentro.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/encuentros/{id}/colaboradores/{userId}
     */
    public function destroy(Request $request, $id, $userId)
    {
        $encuentro = $this->encuentroGestionablePor($request, $id);
        $encuentro->colaboradores()->detach($userId);

        return response()->noContent();
    }

    // Solo el propietario del encuentro, un admin de su mismo centro o el superadmin
    // pueden gestionar (ver/añadir/editar/quitar) colaboradores.
    private function encuentroGestionablePor(Request $request, $id): Encuentro
    {
        $user = $request->user();

        $query = Encuentro::where('id', $id);

        if (!$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->isAdmin() && $user->centro_educativo_id) {
                    $q->orWhere('centro_educativo_id', $user->centro_educativo_id);
                }
            });
        }

        return $query->firstOrFail();
    }
}

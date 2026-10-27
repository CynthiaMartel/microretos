<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateModulosDocenteRequest;
use App\Models\User;
use App\Services\ModulosDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Módulos que imparte un docente: los edita el propio docente («Mi usuario») y el admin de
 * su centro (Gestión de usuarios). Solo se aceptan módulos de la oferta de su centro.
 */
class ModulosDocenteController extends Controller
{
    public function __construct(private ModulosDocenteService $modulos) {}

    /** GET /perfil/modulos */
    public function mios(Request $request): JsonResponse
    {
        $user = $this->docenteActual($request);

        return response()->json($this->respuesta($user));
    }

    /** PUT /perfil/modulos */
    public function actualizarMios(UpdateModulosDocenteRequest $request): JsonResponse
    {
        $user = $this->docenteActual($request);

        return $this->guardar($user, $request->validated('modulo_ids'));
    }

    /** GET /admin/usuarios/{user}/modulos */
    public function delDocente(Request $request, User $user): JsonResponse
    {
        $this->authorize('gestionarModulos', $user);

        return response()->json($this->respuesta($user));
    }

    /** PUT /admin/usuarios/{user}/modulos */
    public function actualizarDelDocente(UpdateModulosDocenteRequest $request, User $user): JsonResponse
    {
        $this->authorize('gestionarModulos', $user);

        return $this->guardar($user, $request->validated('modulo_ids'));
    }

    /** @param array<int, int> $ids */
    private function guardar(User $docente, array $ids): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $validos = $this->modulos->idsValidosParaCentro($docente->centro_educativo_id, $ids);
        // Un módulo que ya tenía asignado y dejó de estar en la oferta (cambio de centro, ciclo
        // retirado) no bloquea el guardado: se descarta. Cualquier otro id ajeno, se rechaza.
        $ajenos = array_diff($ids, $validos, $this->modulos->asignados($docente));
        if ($ajenos) {
            return response()->json([
                'message' => 'Solo se pueden asignar módulos de los ciclos que oferta el centro.',
                'errors'  => ['modulo_ids' => ['Hay módulos que no pertenecen a la oferta del centro.']],
            ], 422);
        }

        $this->modulos->sincronizar($docente, $validos);

        return response()->json($this->respuesta($docente));
    }

    /** @return array{asignados: array<int, int>, oferta: array<int, mixed>, en_proyectos: array<int, int>, num_proyectos: int} */
    private function respuesta(User $docente): array
    {
        $uso = $this->modulos->usoEnProyectos($docente);

        return [
            // Solo los de la oferta actual: el selector no puede mostrar ni quitar los demás
            'asignados' => $this->modulos->idsValidosParaCentro($docente->centro_educativo_id, $this->modulos->asignados($docente)),
            'oferta'    => $this->modulos->oferta($docente->centro_educativo_id),
            // Los módulos que usa en sus proyectos: solo como sugerencia (premarcar, punto en el editor)
            'en_proyectos'  => $uso['modulos'],
            'num_proyectos' => $uso['proyectos'],
        ];
    }

    // Solo docentes (y admins de centro, que también pueden impartir) con centro asignado
    private function docenteActual(Request $request): User
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        abort_unless(($user->isDocente() || $user->isAdmin()) && $user->centro_educativo_id, 403,
            'Solo el profesorado con centro asignado tiene módulos.');

        return $user;
    }
}

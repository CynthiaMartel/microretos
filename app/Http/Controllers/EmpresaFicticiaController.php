<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogoEmpresasRequest;
use App\Http\Requests\CrearEmpresaFicticiaIARequest;
use App\Http\Requests\FamiliasFicticiaIARequest;
use App\Http\Requests\GuardarEmpresaFicticiaIARequest;
use App\Http\Requests\UsarEmpresaCatalogoRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\CentroEducativo;
use App\Models\CicloFormativo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\User;
use App\Services\EmpresaCatalogoService;
use App\Services\EmpresaFicticiaIAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmpresaFicticiaController extends Controller
{
    private const MAX_CATALOGO = 200;

    /**
     * POST /empresas/ficticia-ia/propuesta — "Crear empresa ficticia con IA" del Generador:
     * genera la empresa y su diagnóstico (P1–P5) SIN guardarla, para que el usuario la revise.
     * Docente y admin, siempre en su propio centro; superadmin en el que indique, o en el
     * catálogo DuaLab (sin centro, compartida por todos) con `catalogo: true`.
     */
    public function propuesta(CrearEmpresaFicticiaIARequest $request, EmpresaFicticiaIAService $servicio): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user    = $request->user();
        $datos   = $request->validated();
        $familia = Familia::findOrFail((int) $datos['familiaId']);

        $paraCatalogo = $user->isSuperAdmin() && !empty($datos['catalogo']);
        $centro = $paraCatalogo ? null : $this->centroDestino($user, $datos['centro'] ?? null);
        if (!$paraCatalogo && !$centro) {
            return response()->json(['error' => $this->errorSinCentro($user)], 422);
        }

        // Antes de llamar a la IA: con una familia sin módulos y RA/CE (en los ciclos del
        // centro de destino) la empresa no serviría para generar ningún reto.
        if (!Familia::conDatosParaRetos()->whereKey($familia->id)->exists()) {
            return response()->json(['error' => "{$familia->nombre} aún no tiene módulos ni RA/CE cargados: no se podrían generar retos con esta empresa."], 422);
        }
        $ciclosCentro = $centro ? CicloFormativo::idsDelCentro($centro->nombre) : null;
        if ($ciclosCentro !== null && !Familia::conDatosParaRetos($ciclosCentro)->whereKey($familia->id)->exists()) {
            return response()->json(['error' => "«{$centro->nombre}» no imparte ningún ciclo de {$familia->nombre} con módulos y RA/CE: no se podrían generar retos con esta empresa."], 422);
        }

        $propuesta = $servicio->generarPropuesta($centro, $familia, $user->id);
        if (!$propuesta) {
            return response()->json(['error' => 'La IA no ha podido generar la empresa. Inténtalo de nuevo en unos segundos.'], 502);
        }

        return response()->json([
            'token'      => $propuesta['token'],
            'minutos'    => EmpresaFicticiaIAService::MINUTOS_PROPUESTA,
            'destino'    => $centro === null ? 'Catálogo DuaLab' : $centro->nombre,
            'familia'    => $familia->nombre,
            'familia_id' => $familia->id,
            'empresa'    => $propuesta['columnas'],
        ], 201);
    }

    /** POST /empresas/ficticia-ia — guarda la propuesta revisada (solo su autor, una vez). */
    public function store(GuardarEmpresaFicticiaIARequest $request, EmpresaFicticiaIAService $servicio): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();

        $empresa = $servicio->guardarPropuesta((string) $request->validated('token'), $user->id, $request->diagnostico());
        if (!$empresa) {
            return response()->json(['error' => 'Esta propuesta ya no está disponible (caducó o ya se guardó). Genera otra.'], 410);
        }

        return response()->json([
            'message' => $empresa->es_catalogo ? 'Empresa añadida al catálogo DuaLab' : 'Empresa ficticia guardada',
            'empresa' => new EmpresaResource($empresa),
        ], 201);
    }

    /** GET /empresas/ficticia-ia/propuesta/{token} — recupera una propuesta pendiente (solo su autor). */
    public function verPropuesta(Request $request, EmpresaFicticiaIAService $servicio, string $token): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $propuesta = $servicio->leerPropuesta($token, $user->id);
        if (!$propuesta) {
            return response()->json(['error' => 'Esta propuesta ya no está disponible (caducó o ya se guardó).'], 410);
        }

        return response()->json([
            'token'      => $token,
            'caduca_en'  => $propuesta['caduca_en'],
            'destino'    => $propuesta['centro'] === null ? 'Catálogo DuaLab' : $propuesta['centro']->nombre,
            'familia'    => $propuesta['familia']->nombre,
            'familia_id' => $propuesta['familia']->id,
            'empresa'    => $propuesta['columnas'],
        ]);
    }

    /** DELETE /empresas/ficticia-ia/propuesta/{token} — descarta la propuesta sin guardarla. */
    public function descartar(Request $request, EmpresaFicticiaIAService $servicio, string $token): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $servicio->descartarPropuesta($token, $user->id);
        return response()->json(null, 204);
    }

    /**
     * GET /empresas/ficticia-ia/familias — familias que se pueden elegir al crear una empresa
     * ficticia con IA: las que tienen módulos con RA y CE en los ciclos que imparte el centro de
     * destino (o en cualquier ciclo si el centro aún no tiene ciclos vinculados, o si es para el
     * catálogo DuaLab). Con otra familia la empresa no serviría para generar retos.
     * `sin_datos_en_centro`: familias que el centro imparte pero sin módulos/RA/CE cargados.
     */
    public function familiasDisponibles(FamiliasFicticiaIARequest $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $paraCatalogo = $user->isSuperAdmin() && $request->boolean('catalogo');
        $centro = $paraCatalogo ? null : $this->centroDestino($user, $request->validated('centro'));

        $ciclosCentro = $centro ? CicloFormativo::idsDelCentro($centro->nombre) : null;
        $disponibles  = Familia::conDatosParaRetos($ciclosCentro)->orderBy('nombre')->get(['id', 'nombre']);

        $sinDatosEnCentro = $ciclosCentro === null ? collect() : Familia::whereHas('ciclos', fn ($q) => $q->whereIn('ciclos_formativos.id', $ciclosCentro))
            ->whereNotIn('id', $disponibles->pluck('id'))
            ->orderBy('nombre')->pluck('nombre');

        return response()->json([
            'familias'            => $disponibles->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values(),
            'centro'              => $centro?->nombre,
            'limitado_por_centro' => $ciclosCentro !== null,
            'sin_datos_en_centro' => $sinDatosEnCentro->values(),
        ]);
    }

    /**
     * GET /empresas/catalogo — plantillas del catálogo DuaLab (lectura, todos los roles docentes).
     * Solo las usables: con alguna familia que tenga módulos y RA/CE para generar retos,
     * entre los ciclos que imparte el centro (el del usuario, o el elegido por superadmin).
     */
    public function catalogo(CatalogoEmpresasRequest $request): AnonymousResourceCollection
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user      = $request->user();
        $familiaId = $request->validated('familia_id');
        $centro    = $this->centroDestino($user, $request->validated('centro'));

        $query = Empresa::catalogoUsable(CicloFormativo::idsDelCentro($centro?->nombre))
            ->with('familias:id,nombre')
            ->orderBy('nombre_comercial');
        if ($familiaId) {
            $query->whereHas('familias', fn ($q) => $q->where('familias.id', $familiaId));
        }

        return EmpresaResource::collection($query->take(self::MAX_CATALOGO)->get());
    }

    /**
     * POST /empresas/catalogo/{id}/usar — copia la plantilla en el centro (o reutiliza la
     * copia que ya tuviera) para generar retos con ella y adaptarla.
     */
    public function usarEnCentro(UsarEmpresaCatalogoRequest $request, EmpresaCatalogoService $servicio, int $id): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $plantilla = Empresa::where('es_catalogo', true)->find($id);
        if (!$plantilla) {
            return response()->json(['error' => 'Empresa del catálogo no encontrada'], 404);
        }
        $centro = $this->centroDestino($user, $request->validated('centro'));
        if (!$centro) {
            return response()->json(['error' => $this->errorSinCentro($user)], 422);
        }

        // No se listan, pero se comprueba igualmente: con una petición directa no debe
        // poder copiarse una plantilla con la que no se podría completar ningún reto.
        if (!Empresa::catalogoUsable()->whereKey($plantilla->id)->exists()) {
            return response()->json(['error' => 'Esta empresa del catálogo aún no está disponible: su familia profesional no tiene módulos ni RA/CE para generar retos.'], 422);
        }
        if (!Empresa::catalogoUsable(CicloFormativo::idsDelCentro($centro->nombre))->whereKey($plantilla->id)->exists()) {
            return response()->json(['error' => "«{$centro->nombre}» no imparte ningún ciclo de la familia de esta empresa con módulos y RA/CE, así que no se podría generar un reto con ella."], 422);
        }

        [$empresa, $nueva] = $servicio->usarEnCentro($plantilla, $centro);

        return response()->json([
            'message' => $nueva ? 'Copia creada en tu centro' : 'Tu centro ya tenía una copia: se reutiliza',
            'nueva'   => $nueva,
            'empresa' => new EmpresaResource($empresa),
        ], $nueva ? 201 : 200);
    }

    // Docente/admin: siempre su centro (nunca el que mande el cliente). Superadmin: el indicado.
    private function centroDestino(User $user, ?string $centroNombre): ?CentroEducativo
    {
        if (!$user->isSuperAdmin()) {
            return $user->centroEducativo;
        }
        return $centroNombre ? CentroEducativo::where('nombre', $centroNombre)->first() : null;
    }

    private function errorSinCentro(User $user): string
    {
        return $user->isSuperAdmin()
            ? 'Elige un centro educativo (o marca «Añadir al catálogo DuaLab»).'
            : 'Tu cuenta no tiene un centro educativo asociado. Contacta con el administrador.';
    }
}

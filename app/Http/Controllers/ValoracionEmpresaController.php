<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValorarPropuestaEmpresaRequest;
use App\Http\Resources\ValoracionEmpresaDocenteResource;
use App\Http\Resources\ValoracionEmpresaPublicaResource;
use App\Models\Encuentro;
use App\Models\EncuentroValoracionEmpresa;
use App\Services\ValoracionEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Valoración final de la empresa sobre la propuesta de un encuentro creativo (P3).
 * Docente: /encuentros/{id}/valoracion-empresa (ver, pedir, marcar enviada).
 * Empresa (sin cuenta): /valoracion-empresa/{token} (ver y responder una vez).
 */
class ValoracionEmpresaController extends Controller
{
    public function __construct(private ValoracionEmpresaService $servicio) {}

    // ── Docente ───────────────────────────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $encuentro = Encuentro::visiblesPara($request->user())->with(self::CARGA_DOCENTE)->findOrFail($id);

        return response()->json(new ValoracionEmpresaDocenteResource($encuentro));
    }

    public function solicitar(Request $request, int $id): JsonResponse
    {
        $encuentro = Encuentro::editablesPara($request->user())->with(self::CARGA_DOCENTE)->findOrFail($id);

        $this->servicio->solicitar($encuentro);

        return response()->json(new ValoracionEmpresaDocenteResource($encuentro->load('valoracionEmpresa')));
    }

    public function marcarEnviada(Request $request, int $id): JsonResponse
    {
        $encuentro = Encuentro::editablesPara($request->user())->with(self::CARGA_DOCENTE)->findOrFail($id);
        abort_unless($encuentro->valoracionEmpresa, 404);

        $this->servicio->marcarEnviada($encuentro->valoracionEmpresa);

        return response()->json(new ValoracionEmpresaDocenteResource($encuentro));
    }

    // ── Empresa (por token) ─────────────────────────────────────────────────────

    public function showPublico(string $token): JsonResponse
    {
        $valoracion = $this->porToken($token);

        return response()->json(new ValoracionEmpresaPublicaResource($valoracion));
    }

    public function responder(ValorarPropuestaEmpresaRequest $request, string $token): JsonResponse
    {
        $valoracion = $this->porToken($token);

        if ($valoracion->respondida()) {
            return response()->json(['message' => 'Esta propuesta ya está valorada. Gracias por vuestra participación.'], 409);
        }

        $this->servicio->responder($valoracion, $request->validated());

        return response()->json(['ok' => true]);
    }

    private const CARGA_DOCENTE = ['valoracionEmpresa', 'equipos.fases', 'colaboradores'];

    private function porToken(string $token): EncuentroValoracionEmpresa
    {
        $valoracion = EncuentroValoracionEmpresa::where('token', $token)
            ->with(['encuentro.microproyecto.empresa', 'encuentro.microproyecto.cicloFormativo',
                    'encuentro.equipos.fases', 'encuentro.equipos.prototipos'])
            ->firstOrFail();

        // Un enlace caducado ya no muestra nada (el docente puede pedir uno nuevo)
        abort_if($valoracion->caducado(), 410, 'Este enlace ha caducado. Pide uno nuevo al docente.');

        return $valoracion;
    }
}

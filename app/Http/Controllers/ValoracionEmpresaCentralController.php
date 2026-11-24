<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarValoracionEmpresaCentralRequest;
use App\Http\Resources\ValoracionEmpresaCentralResource;
use App\Mail\ValoracionEmpresaMail;
use App\Models\Encuentro;
use App\Services\ActividadCentralService;
use App\Services\CorreoInfoService;
use App\Services\ValoracionEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DuaLab Central → Red DuaLab → Empresas → «Enviar soluciones a las empresas» (red_dualab,
 * admin_central_dualab y superadmin; middleware de la ruta). DuaLab, como intermediaria, envía a la
 * empresa el enlace para ver las soluciones de los grupos de un encuentro y valorar el reto (P3).
 * Antes lo hacía el docente desde la ficha del encuentro; ahora él solo ve el estado y la respuesta.
 */
class ValoracionEmpresaCentralController extends Controller
{
    // Encuentros de todos los centros: con techo, el más reciente primero
    private const MAX_LISTADO = 300;

    private const CARGA = ['valoracionEmpresa', 'equipos.fases', 'docente:id,name',
        'microproyecto:id,uuid,titulo,estado,empresa_id,microreto_id',
        'microproyecto.empresa:id,nombre_comercial,sector,email_contacto,email_general',
        'microproyecto.microreto:id,empresa_nombre'];

    public function __construct(
        private ValoracionEmpresaService $servicio,
        private ActividadCentralService $actividad,
        private CorreoInfoService $correo,
    ) {}

    public function index(): JsonResponse
    {
        $encuentros = $this->consulta()->orderByDesc('fecha')->orderByDesc('id')->take(self::MAX_LISTADO)->get();

        return response()->json(['data' => ValoracionEmpresaCentralResource::collection($encuentros)]);
    }

    /** Genera el enlace (o uno nuevo si el anterior caducó sin respuesta) */
    public function solicitar(Request $request, int $id): JsonResponse
    {
        $encuentro = $this->consulta()->findOrFail($id);
        $this->servicio->solicitar($encuentro);
        $this->actividad->registrar($request->user(), 'empresas', 'enlace_valoracion',
            "Generó el enlace de valoración del reto «{$encuentro->microproyecto?->titulo}» para la empresa.", $encuentro);

        return $this->respuesta($id);
    }

    /** Envía el enlace a la empresa por info@ y lo marca como enviado */
    public function enviar(EnviarValoracionEmpresaCentralRequest $request, int $id): JsonResponse
    {
        $datos = $request->validated();
        $encuentro = $this->consulta()->findOrFail($id);
        abort_if($encuentro->valoracionEmpresa?->respondida(), 422, 'La empresa ya ha valorado este reto.');

        // Sin enlace vigente, se genera al enviar
        $valoracion = $encuentro->valoracionEmpresa;
        if (!$valoracion || $valoracion->caducado()) {
            $valoracion = $this->servicio->solicitar($encuentro);
        }

        $empresa = $encuentro->microproyecto?->empresa?->nombre_comercial ?? $encuentro->microproyecto?->microreto?->empresa_nombre ?? 'la empresa';
        $reto = $encuentro->microproyecto?->titulo ?: 'Reto de DuaLab';
        $enlace = rtrim((string) config('solicitudes_reto.url_app'), '/') . '/valoracion/' . $valoracion->token;

        $this->correo->enviar(new ValoracionEmpresaMail($valoracion, $enlace, $empresa, $reto, $datos['mensaje'] ?? null),
            $datos['email'], $request->user()->name);
        $this->servicio->marcarEnviada($valoracion);
        $this->actividad->registrar($request->user(), 'empresas', 'enviar_soluciones',
            "Envió a {$empresa} las soluciones del reto «{$reto}» para que lo valore.", $encuentro);

        return $this->respuesta($id);
    }

    /** Enviado por otro canal (el enlace copiado): solo se anota */
    public function marcarEnviada(Request $request, int $id): JsonResponse
    {
        $encuentro = $this->consulta()->findOrFail($id);
        abort_unless($encuentro->valoracionEmpresa, 404);
        $this->servicio->marcarEnviada($encuentro->valoracionEmpresa);
        $this->actividad->registrar($request->user(), 'empresas', 'enviar_soluciones',
            "Marcó como enviado a la empresa el enlace de valoración del reto «{$encuentro->microproyecto?->titulo}».", $encuentro);

        return $this->respuesta($id);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Encuentro> */
    private function consulta()
    {
        return Encuentro::with(self::CARGA)
            ->where('tipo', Encuentro::TIPO_CREATIVO)
            ->whereNotNull('microproyecto_id');
    }

    private function respuesta(int $id): JsonResponse
    {
        return response()->json(['data' => new ValoracionEmpresaCentralResource($this->consulta()->findOrFail($id))]);
    }
}

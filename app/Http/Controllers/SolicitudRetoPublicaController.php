<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarSolicitudRetoRequest;
use App\Http\Requests\GuardarSolicitudRetoRequest;
use App\Http\Resources\MicroretoFichaResource;
use App\Models\SolicitudReto;
use App\Services\SolicitudRetoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Solicitud de retos del docente (app DuaLab, /solicitud-reto/{token}), rutas
 * /public/solicitud-reto/{token}: el docente rellena sin cuenta qué quiere trabajar y, cuando
 * DuaLab le avisa, ve con el enlace del aviso los retos que le envió. El token es la única
 * credencial (SolicitudRetoService).
 */
class SolicitudRetoPublicaController extends Controller
{
    public function __construct(private SolicitudRetoService $solicitudes) {}

    /** GET /public/solicitud-reto/{token} */
    public function show(string $token): JsonResponse
    {
        $solicitud = $this->solicitudes->buscar($token);
        if ($error = $this->noVale($solicitud)) return $error;

        return response()->json(['data' => $this->solicitudes->datosPublicos($solicitud)]);
    }

    /** PUT /public/solicitud-reto/{token} — guarda lo que lleva (al pasar de paso) */
    public function guardar(GuardarSolicitudRetoRequest $request, string $token): JsonResponse
    {
        $solicitud = $this->solicitudes->buscar($token);
        if ($error = $this->noVale($solicitud, abierto: true)) return $error;

        $this->solicitudes->guardar($solicitud, $request->validated());

        return response()->json(['data' => ['guardado' => true]]);
    }

    /** POST /public/solicitud-reto/{token}/enviar — cierra la solicitud */
    public function enviar(EnviarSolicitudRetoRequest $request, string $token): JsonResponse
    {
        $solicitud = $this->solicitudes->buscar($token);
        if ($error = $this->noVale($solicitud, abierto: true)) return $error;

        $this->solicitudes->recibir($solicitud, $request->validated());

        return response()->json(['data' => ['enviado' => true]]);
    }

    /** GET /public/solicitud-reto/{token}/retos — los retos que DuaLab le envió, en solo lectura */
    public function retos(string $token): JsonResponse|AnonymousResourceCollection
    {
        $solicitud = $this->solicitudes->buscar($token);
        if ($error = $this->noVale($solicitud)) return $error;
        if (!$solicitud->avisado_at) {
            return response()->json(['error' => 'Todavía estamos preparando tus retos. Te avisaremos por correo.', 'motivo' => 'sin_retos'], 404);
        }

        return MicroretoFichaResource::collection($this->solicitudes->fichasRetos($solicitud, $solicitud->retos_avisados ?? []));
    }

    /**
     * Respuesta si el enlace no existe o ya no vale, o null si se puede usar. Con `abierto`, además
     * tiene que admitir cambios (sin enviar todavía).
     */
    private function noVale(?SolicitudReto $solicitud, bool $abierto = false): ?JsonResponse
    {
        if (!$solicitud) {
            return response()->json(['error' => 'Este enlace no es válido. Pide uno nuevo a la persona de DuaLab que te lo envió.', 'motivo' => 'no_existe'], 404);
        }
        if (!$solicitud->enlaceVigente()) {
            return response()->json(['error' => 'Este enlace ha caducado. Pide uno nuevo a la persona de DuaLab que te lo envió.', 'motivo' => 'caducado'], 410);
        }
        if ($abierto && !$solicitud->formularioAbierto()) {
            return response()->json(['error' => 'Ya nos has enviado tu solicitud. ¡Gracias!', 'motivo' => 'respondida'], 410);
        }

        return null;
    }
}

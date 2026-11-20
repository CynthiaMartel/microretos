<?php

namespace App\Http\Controllers;

use App\Exceptions\FrontofficeNoDisponible;
use App\Http\Requests\ListarActividadCentralRequest;
use App\Http\Resources\ActividadCentralResource;
use App\Models\ActividadCentral;
use App\Services\FrontofficeBandejaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


/** DuaLab Central → Historial: qué se hizo, quién y cuándo (ActividadCentralService). Solo lectura. */
class ActividadCentralController extends Controller
{
    /** GET /central/historial */
    public function index(ListarActividadCentralRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();
        $query = ActividadCentral::query()->latest('created_at')->latest('id');

        if (!empty($f['seccion'])) $query->where('seccion', $f['seccion']);
        if (!empty($f['criticas'])) $query->where('critica', true);
        if (!empty($f['buscar'])) {
            $texto = '%' . addcslashes($f['buscar'], '%_\\') . '%';
            $query->where(fn ($q) => $q->where('descripcion', 'like', $texto)->orWhere('usuario_nombre', 'like', $texto));
        }

        return ActividadCentralResource::collection($query->paginate(30));
    }

    /**
     * GET /central/historial/envios/{id}: qué se envió (para, asunto, texto, adjuntos). Se lee de la web
     * en el momento: el historial no guarda direcciones ni textos. Los envíos se guardan 4 meses.
     */
    public function envio(Request $request, FrontofficeBandejaService $web, int $id): JsonResponse
    {
        try {
            $envio = $web->verEnvio($id, (string) $request->user()->name);
        } catch (FrontofficeNoDisponible) {
            return response()->json(['error' => 'No se ha podido conectar con la web. Inténtalo de nuevo en un momento.'], 502);
        }
        if ($envio === null) {
            return response()->json(['error' => 'Ese envío ya no está guardado (se conservan 4 meses).'], 404);
        }

        return response()->json(collect($envio)->only(['id', 'tipo', 'para', 'cc', 'cco', 'asunto', 'texto', 'estado', 'enviado_en', 'creado_en', 'adjuntos']));
    }
}

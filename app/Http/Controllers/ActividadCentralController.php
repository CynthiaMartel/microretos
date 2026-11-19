<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarActividadCentralRequest;
use App\Http\Resources\ActividadCentralResource;
use App\Models\ActividadCentral;
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
}

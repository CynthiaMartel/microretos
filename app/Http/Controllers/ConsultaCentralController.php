<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarCentrosCentralRequest;
use App\Http\Requests\ListarEmpresasCentralRequest;
use App\Http\Resources\CentroCentralResource;
use App\Http\Resources\EmpresaCentralResource;
use App\Services\ConsultaCentralService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Red DuaLab → Empresas y Centros: consulta, sin editar (red_dualab, admin_central_dualab y superadmin).
 * Editar o borrar sigue en Base de datos, solo superadmin.
 */
class ConsultaCentralController extends Controller
{
    public function __construct(private readonly ConsultaCentralService $consulta) {}

    /** GET /central/empresas */
    public function empresas(ListarEmpresasCentralRequest $request): AnonymousResourceCollection
    {
        return EmpresaCentralResource::collection($this->consulta->empresas($request->validated()));
    }

    /** GET /central/centros */
    public function centros(ListarCentrosCentralRequest $request): AnonymousResourceCollection
    {
        return CentroCentralResource::collection($this->consulta->centros($request->validated()));
    }
}

<?php

namespace App\Services;

use App\Models\CentroEducativo;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de solo lectura de Red DuaLab (Central → Empresas y Centros): las empresas con los centros
 * a los que están asociadas, como en la App DuaLab, y los centros con sus ciclos y cifras. Nada se
 * edita desde aquí: el alta, la edición y el borrado siguen en Base de datos (superadmin).
 */
class ConsultaCentralService
{
    public const POR_PAGINA = 20;
    public const MAX_POR_PAGINA = 50;

    /** @param array<string, mixed> $filtros  ListarEmpresasCentralRequest */
    public function empresas(array $filtros): LengthAwarePaginator
    {
        $buscar = trim($filtros['buscar'] ?? '');

        return Empresa::query()
            ->with(['familias:familias.id,familias.nombre', 'centroEducativo:id,nombre', 'centrosAsignados:id,nombre'])
            ->withCount('microretos')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre_comercial', 'like', "%{$buscar}%")
                ->orWhere('razon_social', 'like', "%{$buscar}%")
                ->orWhere('sector', 'like', "%{$buscar}%")
                ->orWhere('municipio', 'like', "%{$buscar}%")))
            ->when($filtros['centro_id'] ?? null, fn ($q, $centro) => $q->where(fn ($w) => $w
                ->where('centro_id', $centro)
                ->orWhereHas('centrosAsignados', fn ($c) => $c->where('centro_educativo.id', $centro))))
            ->when($filtros['familia_id'] ?? null, fn ($q, $familia) => $q->whereHas('familias', fn ($f) => $f->where('familias.id', $familia)))
            ->when(($filtros['tipo'] ?? '') === 'real', fn ($q) => $q->where('es_simulada', false))
            ->when(($filtros['tipo'] ?? '') === 'ficticia', fn ($q) => $q->where('es_simulada', true))
            ->orderBy('nombre_comercial')
            ->paginate(min((int) ($filtros['per_page'] ?? self::POR_PAGINA), self::MAX_POR_PAGINA));
    }

    /** @param array<string, mixed> $filtros  ListarCentrosCentralRequest */
    public function centros(array $filtros): LengthAwarePaginator
    {
        $buscar = trim($filtros['buscar'] ?? '');

        return CentroEducativo::query()
            ->with(['ciclos:id,nombre,familia_id', 'ciclos.familia:id,nombre'])
            ->withCount('empresas')
            // Empresas captadas colocadas en el centro (centro_empresa) y cuentas del profesorado
            ->addSelect(['empresas_asignadas_count' => DB::table('centro_empresa')->selectRaw('count(*)')
                ->whereColumn('centro_empresa.centro_id', 'centro_educativo.id')])
            ->addSelect(['docentes_count' => User::query()->selectRaw('count(*)')
                ->whereColumn('users.centro_educativo_id', 'centro_educativo.id')
                ->whereIn('role', [User::ROLE_DOCENTE, User::ROLE_ADMIN])])
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$buscar}%")->orWhere('municipio', 'like', "%{$buscar}%")))
            ->orderBy('nombre')
            ->paginate(min((int) ($filtros['per_page'] ?? self::POR_PAGINA), self::MAX_POR_PAGINA));
    }
}

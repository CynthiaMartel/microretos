<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado de la valoración final de la empresa de un encuentro, para el docente (solo lectura).
 * Recurso = Encuentro con 'valoracionEmpresa' y 'equipos.fases' cargados. Sin el token: el enlace
 * lo genera y lo envía DuaLab desde Central (ValoracionEmpresaCentralResource).
 */
class ValoracionEmpresaDocenteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $v = $this->valoracionEmpresa;
        $puedeEditar = $this->resource->esEditablePor($request->user());

        $equipos = $this->equipos;
        $conEntrega = $equipos->filter(fn ($e) => (bool) $e->fases->firstWhere('numero_fase', 3)?->completada)->count();

        return [
            'puede_editar'      => $puedeEditar,
            'grupos_total'      => $equipos->count(),
            'grupos_con_entrega' => $conEntrega,
            'solicitada'        => (bool) $v,
            'token_expira_en'   => $v?->token_expira_en?->toIso8601String(),
            'caducado'          => $v ? $v->caducado() : false,
            'enviado_en'        => $v?->enviado_en?->toIso8601String(),
            'respondido_en'     => $v?->respondido_en?->toIso8601String(),
            'valoracion_global' => $v?->valoracion_global,
            'respuestas'        => $v?->respuestas,
            'comentarios'       => $v?->comentarios,
        ];
    }
}

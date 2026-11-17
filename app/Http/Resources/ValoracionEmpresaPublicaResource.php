<?php

namespace App\Http\Resources;

use App\Support\UrlSegura;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo que ve la empresa (sin cuenta) al valorar la propuesta: la propuesta y la solución que
 * entregó cada grupo en F3. Lista blanca estricta — protección de datos: nada del alumnado (ni
 * alias), ni códigos, ni tokens. Recurso = EncuentroValoracionEmpresa con encuentro.microproyecto
 * y encuentro.equipos.{fases,prototipos} cargados.
 */
class ValoracionEmpresaPublicaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $encuentro = $this->encuentro;
        $mp = $encuentro->microproyecto;

        return [
            'propuesta' => [
                'titulo'        => $mp?->titulo,
                'pregunta_reto' => $mp?->diseno_reto['pregunta_reto'] ?? null,
                'empresa'       => $mp?->empresa?->nombre_comercial ?? ($mp?->datos_empresa['nombre'] ?? null),
                'centro'        => $mp?->datos_centro['nombre'] ?? $encuentro->centro_educativo,
                'ciclo'         => $mp?->cicloFormativo?->nombre ?? $encuentro->ciclo_formativo,
                'objetivos'     => $mp?->objetivos['lista'] ?? [],
            ],
            'encuentro' => [
                'fecha'     => $encuentro->fecha?->format('Y-m-d'),
                'fecha_fin' => $encuentro->fecha_fin?->format('Y-m-d'),
            ],
            'grupos' => $encuentro->equipos->map(function ($e) {
                $f3 = $e->fases->firstWhere('numero_fase', 3);

                return [
                    'nombre'            => $e->numero_equipo ? "Grupo {$e->numero_equipo}" : ($e->nombre ?: 'Grupo'),
                    'fases_completadas' => $e->fases->where('completada', true)->count(),
                    'entregada'         => (bool) $f3?->completada,
                    'entrega'           => $f3?->completada ? [
                        'descripcion' => $f3->datos['descripcion_entregable'] ?? null,
                        'url'         => UrlSegura::http($f3->datos['url_entregable'] ?? null),
                    ] : null,
                    'prototipos' => $e->prototipos->map(fn ($p) => [
                        'label' => $p->label,
                        'url'   => UrlSegura::http($p->url),
                        'tipo'  => $p->resource_type,
                    ])->filter(fn ($p) => $p['url'])->values(),
                ];
            })->values(),
            'valoracion' => [
                'respondida'        => $this->respondida(),
                'token_expira_en'   => $this->token_expira_en?->toIso8601String(),
                'valoracion_global' => $this->respondida() ? $this->valoracion_global : null,
                'respuestas'        => $this->respondida() ? $this->respuestas : null,
                'comentarios'       => $this->respondida() ? $this->comentarios : null,
            ],
        ];
    }
}

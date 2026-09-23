<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "Resolución del alumnado" de un equipo, para el escaparate público (proyecto ya
 * completado y visible_publico=true). Whitelist explícita — MUCHO más estricta que el
 * endpoint docente equivalente (EncuentroController::equiposDeProyecto):
 *   - Miembros: SOLO `alias` (Kahoot-style, autogenerado) + `rol`. NUNCA `nombre` — es
 *     un campo `encrypted` en EquipoMiembro porque es el nombre real del alumnado,
 *     mayoritariamente menor de edad. El backoffice sí lo muestra a docentes
 *     autenticados; aquí no hay sesión ni control de quién mira.
 *   - Reflexiones: solo el texto de `respuestas`, nunca `autor_nombre` (que también
 *     guarda el nombre real). Las individuales se listan sin ninguna referencia a
 *     quién las escribió.
 *   - `diagnostico_final`: es texto generado por IA a partir de fases/evaluación, con
 *     instrucción explícita de no repetir datos identificativos — seguro de exponer.
 *   - Fases (datos/nota_docente/observaciones_docente) NO se exponen: son notas de
 *     seguimiento interno del docente, no pensadas para publicarse.
 */
class EquipoResolucionPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nombre' => $this->nombre,

            'miembros' => $this->whenLoaded('miembros', fn () => $this->miembros
                ->map(fn ($m) => ['alias' => $m->alias, 'rol' => $m->rol])
                ->values()),

            'diagnostico_final' => $this->diagnostico_final ? [
                'resumen'           => $this->diagnostico_final['resumen'] ?? null,
                'fortalezas'        => $this->diagnostico_final['fortalezas'] ?? [],
                'areas_mejora'      => $this->diagnostico_final['areas_mejora'] ?? [],
                'valoracion_ra_ce'  => $this->diagnostico_final['valoracion_ra_ce'] ?? null,
                'conclusion'        => $this->diagnostico_final['conclusion'] ?? null,
            ] : null,

            'reflexion_grupal' => $this->whenLoaded('reflexiones', fn () =>
                optional($this->reflexiones->firstWhere('tipo', 'grupal'))->respuestas),

            'reflexiones_individuales' => $this->whenLoaded('reflexiones', fn () => $this->reflexiones
                ->where('tipo', 'individual')
                ->map(fn ($r) => $r->respuestas)
                ->values()),
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerarMicroretoRequest extends FormRequest
{
    // Techo pedagógico y de tokens para los RA/CE fijados a mano: un microreto que
    // pretende trabajar decenas de CE pierde coherencia y dispara el tamaño del prompt.
    public const MAX_RA_SELECCIONADOS = 8;
    public const MAX_CE_SELECCIONADOS = 12;

    protected function prepareForValidation(): void
    {
        // El frontend manda empresaId (camelCase), normalizamos antes de validar
        $this->merge([
            'empresa_id' => $this->empresa_id ?? $this->empresaId,
        ]);
    }

    public function authorize(): bool
    {
        // La autorización de rol (docente/admin/superadmin) ya la resuelve
        // el middleware 'docente' en routes/api.php; la pertenencia al centro
        // se comprueba aparte en el controller (perteneceAlCentroDe).
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'        => 'required|integer|exists:empresas,id',
            'empresaNombre'     => 'required|string',
            'empresaSector'     => 'required|string',
            'friccionProblema'  => 'required|string|max:1200',
            // Resto del diagnóstico del paso 2 (llega al prompt): mismos límites que la empresa.
            'diaANormal'          => 'sometimes|nullable|string|max:1000',
            'friccionArea'        => 'sometimes|nullable|string|max:400',
            'restricciones'       => 'sometimes|nullable|string|max:2000',
            'loQueNoQuieren'      => 'sometimes|nullable|string|max:500',
            'expectativasAlumno'  => 'sometimes|nullable|string|max:800',
            'consecuencias'       => 'sometimes|nullable|array|max:20',
            'consecuencias.*'     => 'nullable|string|max:300',
            'ciclo_nombre'      => 'required|string',
            'ciclo_id'          => 'required|integer|exists:ciclos_formativos,id',
            'nivelGrupo'        => 'required|string',
            'cursoSeleccionado' => 'required|in:1,2,ambos_cursos',
            'modulo_id'         => 'nullable|array|max:20',
            'modulo_id.*'       => 'integer|distinct|exists:modulos,id',
            'cantidad'          => 'required|integer|min:1|max:5',
            'familia'           => 'nullable|string',
            // Paso 3, opcional: tipo de propuesta que el docente quiere trabajar con su alumnado.
            'enfoqueReto'       => 'nullable|string|max:500',

            // RA/CE fijados a mano por el docente — solo tienen sentido con módulos forzados.
            // La pertenencia RA→módulo forzado y CE→RA se comprueba en el controller
            // (RaCeCatalogoService::resolverSeleccionDocente), nunca se confía en el cliente.
            'seleccion_ra_ce'            => 'sometimes|nullable|array|max:' . self::MAX_RA_SELECCIONADOS,
            'seleccion_ra_ce.*.ra_id'    => 'required|integer|distinct|exists:resultados_aprendizaje,id',
            'seleccion_ra_ce.*.ce_ids'   => 'required|array|min:1',
            'seleccion_ra_ce.*.ce_ids.*' => 'integer|distinct|exists:criterios_evaluacion,id',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $seleccion = $this->input('seleccion_ra_ce');
                if (!is_array($seleccion) || count($seleccion) === 0) return;

                if (!is_array($this->input('modulo_id')) || count($this->input('modulo_id')) === 0) {
                    $validator->errors()->add('seleccion_ra_ce', 'Para fijar RA/CE hay que forzar al menos un módulo.');
                    return;
                }

                $totalCe = collect($seleccion)->sum(fn ($item) => is_array($item['ce_ids'] ?? null) ? count($item['ce_ids']) : 0);
                if ($totalCe > self::MAX_CE_SELECCIONADOS) {
                    $validator->errors()->add('seleccion_ra_ce', 'Puedes fijar como máximo ' . self::MAX_CE_SELECCIONADOS . ' criterios de evaluación en un mismo reto.');
                }
            },
        ];
    }
}

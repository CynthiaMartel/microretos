<?php

namespace App\Http\Requests;

use App\Models\CicloFormativo;
use App\Models\CriterioEvaluacion;
use App\Models\Modulo;
use App\Models\ResultadoAprendizaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /public/solicitud-reto/{token}/enviar — el docente envía su solicitud de retos. Sin
 * usuario: el token lo comprueba el controller.
 *
 * Una solicitud trae uno o varios bloques («¿Quieres otro más?»): cada bloque es lo que el
 * generador necesita para crear retos —familia, ciclo, curso, módulos, RA/CE y qué tipo de
 * propuesta quiere trabajar (enfoqueReto)—, y DuaLab lo abre en el generador por separado. Lo
 * demás (grupo, sesión, tareas en la empresa…) es común y de uso interno.
 *
 * Los ids del currículo de cada bloque se comprueban entre sí (ciclo de la familia, módulos del
 * ciclo y del curso, RA de esos módulos y CE de su RA): un id ajeno es una petición manipulada o
 * desfasada y se rechaza. Mismos límites que el generador (GenerarMicroretoRequest).
 */
class EnviarSolicitudRetoRequest extends FormRequest
{
    public const NIVELES        = ['Bajo', 'Medio', 'Alto'];
    public const PUNTOS_TEMARIO = ['primera_vez', 'en_curso', 'repaso'];
    public const MAX_BLOQUES    = 5;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombreDocente'    => 'required|string|max:150',
            'centroNombre'     => 'required|string|max:255',

            'bloques'                => 'required|array|min:1|max:' . self::MAX_BLOQUES,
            'bloques.*.familiaId'    => 'required|integer|exists:familias,id',
            'bloques.*.cicloId'      => 'required|integer|exists:ciclos_formativos,id',
            'bloques.*.curso'        => 'required|in:1,2,ambos_cursos',
            'bloques.*.moduloIds'    => 'nullable|array|max:20',
            'bloques.*.moduloIds.*'  => 'integer|exists:modulos,id',
            'bloques.*.seleccionRaCe'               => 'nullable|array|max:' . GenerarMicroretoRequest::MAX_RA_SELECCIONADOS,
            'bloques.*.seleccionRaCe.*.ra_id'       => 'required|integer|exists:resultados_aprendizaje,id',
            // Para volver a pintar la selección al reabrir el enlace; la pertenencia se comprueba con ra_id
            'bloques.*.seleccionRaCe.*.modulo_id'   => 'nullable|integer',
            'bloques.*.seleccionRaCe.*.ce_ids'      => 'required|array|min:1',
            'bloques.*.seleccionRaCe.*.ce_ids.*'    => 'integer|exists:criterios_evaluacion,id',
            // Lo mismo que pregunta el generador en el paso 3 (enfoqueReto)
            'bloques.*.tipoPropuesta' => 'required|string|max:1000',

            // Sin nivel: lo decide DuaLab
            'nivelGrupo'       => 'nullable|in:' . implode(',', self::NIVELES),
            'puntoTemario'     => 'nullable|in:' . implode(',', self::PUNTOS_TEMARIO),
            'unidadTemario'    => 'nullable|string|max:150',
            'sesionFecha'      => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'sesionHoraInicio' => 'nullable|required_with:sesionHoraFin|date_format:H:i',
            'sesionHoraFin'    => 'nullable|date_format:H:i|after:sesionHoraInicio',

            // Uso interno de DuaLab (elegir empresa, contexto)
            'tareasEmpresa'    => 'nullable|string|max:1000',
            'entregable'       => 'nullable|string|max:800',
            'observaciones'    => 'nullable|string|max:1000',

            'aceptaPrivacidad'  => 'accepted',
            'privacidadVersion' => 'required|string|max:30|in:' . config('solicitudes_reto.privacidad.version'),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;
            foreach ((array) ($this->input('bloques') ?? []) as $i => $bloque) {
                if (is_array($bloque)) $this->comprobarCurriculo($validator, $bloque, "bloques.{$i}.");
            }
        }];
    }

    /** @param array<string, mixed> $b  un bloque */
    private function comprobarCurriculo(Validator $validator, array $b, string $prefijo): void
    {
        $familiaId = $b['familiaId'] ?? null;
        $cicloId   = $b['cicloId'] ?? null;
        $moduloIds = array_map('intval', (array) ($b['moduloIds'] ?? []));
        $seleccion = (array) ($b['seleccionRaCe'] ?? []);

        if ($cicloId && $familiaId && !CicloFormativo::whereKey($cicloId)->where('familia_id', $familiaId)->exists()) {
            $validator->errors()->add("{$prefijo}cicloId", 'El ciclo no pertenece a la familia elegida.');
            return;
        }

        if ($moduloIds) {
            if (count($moduloIds) !== count(array_unique($moduloIds))) {
                $validator->errors()->add("{$prefijo}moduloIds", 'Hay un módulo repetido.');
                return;
            }
            $modulos = Modulo::whereIn('id', $moduloIds)->get(['id', 'idcicloformativo', 'curso']);
            if (!$cicloId || $modulos->contains(fn ($m) => (int) $m->idcicloformativo !== (int) $cicloId)) {
                $validator->errors()->add("{$prefijo}moduloIds", 'Algún módulo no pertenece al ciclo elegido.');
                return;
            }
            $curso = (string) ($b['curso'] ?? '');
            if (in_array($curso, ['1', '2'], true) && $modulos->contains(fn ($m) => (string) $m->curso !== $curso)) {
                $validator->errors()->add("{$prefijo}moduloIds", 'Algún módulo no es del curso elegido.');
                return;
            }
            // Igual que el generador: en «ambos cursos», si se eligen módulos, al menos uno de cada curso
            if ($curso === 'ambos_cursos' && $modulos->pluck('curso')->map(fn ($c) => (int) $c)->unique()->count() < 2) {
                $validator->errors()->add("{$prefijo}moduloIds", 'Con los dos cursos, elige al menos un módulo de 1.º y uno de 2.º.');
                return;
            }
        }

        if (!$seleccion) return;
        if (!$moduloIds) {
            $validator->errors()->add("{$prefijo}seleccionRaCe", 'Para elegir RA/CE hay que elegir al menos un módulo.');
            return;
        }

        $raIds = array_map(fn ($s) => (int) $s['ra_id'], $seleccion);
        if (count($raIds) !== count(array_unique($raIds))
            || ResultadoAprendizaje::whereIn('id', $raIds)->whereIn('idmodulo', $moduloIds)->count() !== count($raIds)) {
            $validator->errors()->add("{$prefijo}seleccionRaCe", 'Algún resultado de aprendizaje no es de los módulos elegidos.');
            return;
        }

        $totalCe = 0;
        foreach ($seleccion as $item) {
            $ceIds = array_unique(array_map('intval', $item['ce_ids']));
            $totalCe += count($ceIds);
            if (CriterioEvaluacion::whereIn('id', $ceIds)->where('idresultadoaprendizaje', (int) $item['ra_id'])->count() !== count($ceIds)) {
                $validator->errors()->add("{$prefijo}seleccionRaCe", 'Algún criterio de evaluación no es de su resultado de aprendizaje.');
                return;
            }
        }
        if ($totalCe > GenerarMicroretoRequest::MAX_CE_SELECCIONADOS) {
            $validator->errors()->add("{$prefijo}seleccionRaCe", 'Puedes elegir como máximo ' . GenerarMicroretoRequest::MAX_CE_SELECCIONADOS . ' criterios de evaluación.');
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'aceptaPrivacidad.accepted'        => 'Tienes que leer y aceptar la información sobre protección de datos.',
            'privacidadVersion.in'             => 'La información de protección de datos ha cambiado: recarga la página para leerla.',
            'sesionFecha.after_or_equal'       => 'La fecha de la sesión no puede ser anterior a hoy.',
            'sesionHoraFin.after'              => 'La hora de fin tiene que ser posterior a la de inicio.',
            'bloques.required'                 => 'Cuéntanos al menos un ciclo y curso que quieras trabajar.',
            'bloques.*.tipoPropuesta.required' => 'Cuéntanos qué tipo de propuesta quieres trabajar.',
        ];
    }
}

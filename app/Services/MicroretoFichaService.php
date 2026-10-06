<?php

namespace App\Services;

use App\Models\CicloFormativo;
use App\Models\Empresa;
use App\Models\Microreto;
use App\Models\Modulo;
use Illuminate\Support\Collection;

class MicroretoFichaService
{
    /**
     * Añade a la instancia los campos derivados que espera la ficha de reto
     * (MicroretoModal.vue): centro_educativo, familia, empresa_es_simulada y curso.
     * Requiere que $reto tenga cargadas las relaciones empresa.centroEducativo y empresa.familias.
     *
     * Pensado para UN solo microreto (show()): derivarCurso()/derivarCursoDeEvaluacion()
     * lanzan queries propias. Para listados, usar enriquecerLote() en su lugar.
     */
    public static function enriquecer(Microreto $reto): Microreto
    {
        self::aplicarDatosEmpresa($reto);

        if (is_null($reto->curso)) {
            $reto->curso = self::derivarCurso($reto->ciclo_id, $reto->ciclo, $reto->modulo);
        }

        if (is_null($reto->curso) && $reto->evaluacion_oficial && $reto->ciclo_id) {
            $reto->curso = self::derivarCursoDeEvaluacion($reto->ciclo_id, $reto->evaluacion_oficial);
        }

        self::anotarCursoPorModulo($reto);

        return $reto;
    }

    /**
     * Añade `curso` (1|2|null) a cada entrada de evaluacion_oficial, buscando el módulo
     * real por nombre dentro del ciclo del reto — así la ficha puede mostrar a qué curso
     * pertenece cada módulo sugerido por la IA, sin depender de que se haya guardado en
     * su momento (funciona igual para retos antiguos y nuevos). Solo para enriquecer() —
     * enriquecerLote() no lo necesita porque los listados no muestran este detalle.
     */
    private static function anotarCursoPorModulo(Microreto $reto): void
    {
        if (empty($reto->evaluacion_oficial) || !$reto->ciclo_id) {
            return;
        }

        $cache = [];
        $evaluacion = $reto->evaluacion_oficial;
        foreach ($evaluacion as &$item) {
            $nombreModulo = $item['modulo'] ?? null;
            if (!$nombreModulo) {
                continue;
            }
            if (!array_key_exists($nombreModulo, $cache)) {
                $cache[$nombreModulo] = self::buscarCursoDeModulo($reto->ciclo_id, $nombreModulo);
            }
            $item['curso'] = $cache[$nombreModulo];
        }
        unset($item);

        $reto->evaluacion_oficial = $evaluacion;
    }

    /**
     * Igual que enriquecer(), pero pensado para listados (index()): precarga módulos y ciclos
     * UNA sola vez y deriva el curso en memoria, en vez de hacer las queries de
     * derivarCurso()/derivarCursoDeEvaluacion() por cada fila (evita N+1 sobre el listado completo).
     * No aplica el fallback de evaluacion_oficial (tampoco lo aplicaba la lógica que sustituye).
     */
    public static function enriquecerLote(Collection $retos): Collection
    {
        $modulosPorCiclo = Modulo::select('idcicloformativo', 'nombre', 'curso')
            ->get()
            ->groupBy('idcicloformativo');
        $ciclosPorNombre = CicloFormativo::pluck('id', 'nombre');

        return $retos->each(function (Microreto $reto) use ($modulosPorCiclo, $ciclosPorNombre) {
            self::aplicarDatosEmpresa($reto);

            if (is_null($reto->curso) && $reto->modulo && $reto->modulo !== 'Transversal') {
                $cicloId = $reto->ciclo_id ?? $ciclosPorNombre->get($reto->ciclo);
                if ($cicloId) {
                    $primerModulo    = trim(explode(' y ', $reto->modulo)[0]);
                    $modulosDelCiclo = $modulosPorCiclo->get($cicloId, collect());
                    $modulo = $modulosDelCiclo->first(fn($m) =>
                        $m->nombre === $primerModulo ||
                        str_starts_with($m->nombre, rtrim($primerModulo, '.'))
                    );
                    $reto->curso = $modulo?->curso;
                }
            }
        });
    }

    // Campos del diagnóstico que se copian al reto (los mismos que expone la ficha).
    public const CAMPOS_DIAGNOSTICO = [
        'sector', 'tamano', 'dia_a_normal', 'friccion_area', 'friccion_problema',
        'consecuencias', 'restricciones', 'lo_que_no_quieren', 'expectativas_alumno',
    ];

    /**
     * Copia del diagnóstico actual de la empresa, para guardarla en el reto. Siempre desde
     * la BD, nunca desde lo que mande el cliente: nadie puede atribuir a una empresa (real)
     * respuestas que no dio.
     *
     * @return array<string, mixed>|null
     */
    public static function copiaDiagnostico(?Empresa $empresa): ?array
    {
        if (!$empresa) return null;
        $copia = [];
        foreach (self::CAMPOS_DIAGNOSTICO as $campo) {
            $copia[$campo] = $empresa->getAttribute($campo);
        }
        $copia['capturado_en'] = now()->toIso8601String();
        return $copia;
    }

    /**
     * Diagnóstico con el que se genera el reto (el de la pantalla del paso 2), en columnas.
     *
     * @param array<string, mixed> $peticion  datos validados de GenerarMicroretoRequest
     * @return array<string, string>
     */
    public static function diagnosticoDePeticion(array $peticion): array
    {
        $texto = fn ($v) => trim(strip_tags(is_string($v) ? $v : ''));
        $consecuencias = is_array($peticion['consecuencias'] ?? null)
            ? implode(', ', array_filter(array_map($texto, $peticion['consecuencias'])))
            : '';
        return [
            'dia_a_normal'        => $texto($peticion['diaANormal'] ?? null),
            'friccion_area'       => $texto($peticion['friccionArea'] ?? null),
            'friccion_problema'   => $texto($peticion['friccionProblema'] ?? null),
            'restricciones'       => $texto($peticion['restricciones'] ?? null),
            'consecuencias'       => $consecuencias,
            'lo_que_no_quieren'   => $texto($peticion['loQueNoQuieren'] ?? null),
            'expectativas_alumno' => $texto($peticion['expectativasAlumno'] ?? null),
        ];
    }

    /**
     * Firma (HMAC con APP_KEY) del diagnóstico usado al generar, ligada a usuario y empresa: al
     * guardar el reto solo se acepta un diagnóstico «ajustado» que haya pasado por generar().
     *
     * @param array<string, string> $usado
     */
    public static function firmarDiagnosticoUsado(array $usado, int $empresaId, int $userId): string
    {
        ksort($usado);
        return hash_hmac('sha256', "{$userId}#{$empresaId}#" . json_encode($usado, JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }

    /**
     * Copia del diagnóstico que se guarda en el reto. Si llega un diagnóstico ajustado con firma
     * válida (generado con cambios sin guardar en la empresa), se guarda ese, marcado como
     * «modificado_en_reto»; si no, la copia de la empresa en BD.
     *
     * @param array<string, mixed> $datos  datos validados del reto a guardar
     * @return array<string, mixed>|null
     */
    public static function copiaDiagnosticoParaGuardar(?Empresa $empresa, array $datos, int $userId): ?array
    {
        $copia = self::copiaDiagnostico($empresa);
        $usado = $datos['diagnostico_usado'] ?? null;
        $firma = $datos['diagnostico_firma'] ?? null;
        if (!$copia || !$empresa || !is_array($usado) || !is_string($firma)) return $copia;

        $usado = array_map(fn ($v) => is_string($v) ? $v : '', array_intersect_key($usado, array_flip(self::CAMPOS_DIAGNOSTICO)));
        if (!hash_equals(self::firmarDiagnosticoUsado($usado, $empresa->id, $userId), $firma)) return $copia;

        return array_merge($copia, $usado, ['modificado_en_reto' => true]);
    }

    // Listas guardadas como texto "a, b, c": se comparan como conjunto (orden/espacios no cuentan).
    private const CAMPOS_LISTA = ['consecuencias', 'restricciones'];

    /**
     * ¿El diagnóstico actual de la empresa difiere de la copia guardada en el reto? Compara
     * contenido (no fechas): updated_at de la empresa cambia también al tocar el teléfono.
     *
     * @param array<string, mixed> $copia
     */
    public static function diagnosticoCambiado(array $copia, Empresa $empresa): bool
    {
        $normalizar = function (string $campo, mixed $valor): string {
            $texto = trim((string) $valor);
            if (!in_array($campo, self::CAMPOS_LISTA, true)) return $texto;
            $items = array_filter(array_map('trim', explode(',', $texto)), fn ($i) => $i !== '');
            sort($items);
            return implode(',', $items);
        };
        foreach (self::CAMPOS_DIAGNOSTICO as $campo) {
            if (!array_key_exists($campo, $copia)) continue;
            if ($normalizar($campo, $copia[$campo]) !== $normalizar($campo, $empresa->getAttribute($campo))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fecha en que se recogió el diagnóstico del reto, o null si no se conoce: retos sin copia,
     * o copias rellenadas después (microretos:capturar-diagnostico), cuya fecha es la del
     * relleno y no la del reto — mostrarla confundiría.
     */
    public static function fechaCopiaDiagnostico(Microreto $reto): ?string
    {
        $copia = is_array($reto->diagnostico_empresa) ? $reto->diagnostico_empresa : null;
        if (!$copia || empty($copia['capturado_en']) || !$reto->created_at) return null;
        $capturado = \Illuminate\Support\Carbon::parse($copia['capturado_en']);
        return $capturado->diffInHours($reto->created_at, true) <= 24 ? $capturado->toIso8601String() : null;
    }

    private static function aplicarDatosEmpresa(Microreto $reto): void
    {
        $reto->es_simulado = (bool) $reto->es_simulado;

        // Con copia guardada, el reto muestra el diagnóstico tal como era al crearlo. Se
        // sustituye por un clon: la misma instancia de Empresa la comparten todos los retos
        // de esa empresa en una carga ansiosa, y modificarla contaminaría a los demás.
        if ($reto->empresa && is_array($reto->diagnostico_empresa)) {
            // Antes de sustituir la empresa por la copia: ¿ha cambiado desde que se creó el reto?
            // Un diagnóstico ajustado para el reto ya difiere a propósito: se explica con su propia
            // etiqueta y no se avisa como «desactualizado».
            $modificado = !empty($reto->diagnostico_empresa['modificado_en_reto']);
            $reto->setAttribute('diagnostico_modificado_en_reto', $modificado);
            $reto->setAttribute('diagnostico_desactualizado', !$modificado && self::diagnosticoCambiado($reto->diagnostico_empresa, $reto->empresa));
            $reto->setAttribute('diagnostico_recogido_en', self::fechaCopiaDiagnostico($reto));
            $clon = clone $reto->empresa;
            $clon->forceFill(array_intersect_key($reto->diagnostico_empresa, array_flip(self::CAMPOS_DIAGNOSTICO)));
            $reto->setRelation('empresa', $clon);
        }

        if ($reto->empresa) {
            $reto->centro_educativo = $reto->empresa->centroEducativo?->nombre
                ?? $reto->empresa->centro_educativo
                ?? 'Centro Desconocido';

            $reto->familia = $reto->empresa->familias->first()?->nombre
                ?? 'Familia Desconocida';

            $reto->empresa_es_simulada = (bool) $reto->empresa->es_simulada;
        } else {
            $reto->centro_educativo    = 'Centro Desconocido';
            $reto->familia             = 'Familia Desconocida';
            $reto->empresa_es_simulada = false;
        }
    }

    /**
     * Deduce el número de curso (1 o 2) a partir del módulo guardado en el microreto.
     * Primero intenta por ciclo_id (FK), luego por nombre de ciclo (legacy).
     * Tolerante al punto final en nombres de módulo (datos BOE vs. texto libre).
     * Usado como fallback en guardarEnBD()/guardarLote() cuando el frontend no manda
     * `curso` ya calculado. No tiene en cuenta `multimodulo` (multi-módulo del mismo
     * curso) ni el Escenario B (ambos cursos) — esos casos siempre llegan con `curso`
     * ya resuelto explícitamente por el generador.
     */
    public static function derivarCurso(?int $cicloId, ?string $cicloNombre, ?string $moduloTexto): ?int
    {
        if (!$moduloTexto || $moduloTexto === 'Transversal') {
            return null;
        }

        // El campo 'modulo' puede ser "Módulo A y Módulo B" — tomamos el primero
        $primerModulo = trim(explode(' y ', $moduloTexto)[0]);

        $cicloIdResuelto = $cicloId;

        if (!$cicloIdResuelto && $cicloNombre) {
            $cicloIdResuelto = CicloFormativo::where('nombre', $cicloNombre)->value('id');
        }

        if (!$cicloIdResuelto) {
            return null;
        }

        // Intento exacto primero; si falla, toleramos punto final (nombres BOE acaban en '.')
        $curso = Modulo::where('idcicloformativo', $cicloIdResuelto)
            ->where('nombre', $primerModulo)
            ->value('curso');

        if (is_null($curso)) {
            $curso = Modulo::where('idcicloformativo', $cicloIdResuelto)
                ->where('nombre', 'LIKE', rtrim($primerModulo, '.') . '%')
                ->orderByRaw('LENGTH(nombre) ASC') // preferir el más corto (más específico)
                ->value('curso');
        }

        return $curso;
    }

    /**
     * Fallback: cuando modulo = 'Transversal', intentamos derivar el curso
     * mirando los módulos referenciados en el JSON de evaluacion_oficial.
     */
    private static function derivarCursoDeEvaluacion(int $cicloId, array $evaluacionOficial): ?int
    {
        foreach ($evaluacionOficial as $item) {
            $nombreModulo = $item['modulo'] ?? null;
            if (!$nombreModulo) continue;

            $curso = self::buscarCursoDeModulo($cicloId, $nombreModulo);
            if (!is_null($curso)) {
                return $curso;
            }
        }

        return null;
    }

    /**
     * Busca el curso (1|2) de un módulo por nombre dentro de un ciclo. Tolerante al
     * punto final en nombres BOE (p. ej. "Instalaciones eléctricas interiores." vs
     * el texto libre guardado sin el punto).
     */
    private static function buscarCursoDeModulo(int $cicloId, string $nombreModulo): ?int
    {
        return Modulo::where('idcicloformativo', $cicloId)
            ->where('nombre', 'LIKE', rtrim($nombreModulo, '.') . '%')
            ->orderByRaw('LENGTH(nombre) ASC')
            ->value('curso');
    }
}

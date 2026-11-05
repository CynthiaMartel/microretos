<?php

namespace App\Services;

use App\Http\Controllers\MicroretoIAController;
use App\Http\Requests\GenerarMicroretoRequest;
use App\Models\CicloFormativo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Microreto;
use App\Models\MicroretoBorrador;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retos que el comercial genera delante de la empresa (demostración). Se guardan como
 * borradores (microreto_borradores) y solo llegan a la biblioteca cuando el superadmin los
 * publica en uno o varios centros de la empresa: un Microreto por centro.
 */
class BorradorRetoService
{
    // Campos del reto generado que pasan a `microretos` al publicar (lista blanca de Microreto::$fillable)
    private const CAMPOS_RETO = [
        'titulo', 'subtitulo', 'empresa_nombre', 'quien_es', 'dia_a_dia', 'pregunta_reto',
        'dificultades', 'que_necesitan', 'limitaciones', 'prototipos', 'ods_sugeridos',
        'soft_skills', 'evaluacion_oficial', 'tips_profesorado', 'variantes',
    ];

    // Firmas que emite generar() con el id del comercial: se verifican al publicar con ese id
    private const CAMPOS_FIRMA = [
        'ra_ce_origen', 'ra_ce_firma', 'interes_familia', 'interes_usado', 'interes_firma',
        'aviso_cobertura_incompleta', 'aviso_aplicacion_incompleta',
    ];

    public function __construct(
        private CaptacionEmpresaService $captacion,
        private RaCeCatalogoService $raCe,
    ) {}

    /**
     * Genera los retos con la misma lógica y el mismo prompt que el Generador de Retos
     * (MicroretoIAController::generar), con los datos de la empresa guardados en la BD.
     *
     * @param array<string, mixed> $validated  GenerarRetosComercialRequest
     * @return Collection<int, MicroretoBorrador>
     */
    public function generar(Empresa $empresa, User $comercial, array $validated): Collection
    {
        if (!$this->captacion->tieneDiagnosticoMinimo($empresa)) {
            throw ValidationException::withMessages([
                'empresa' => 'Completa primero la entrevista (actividad, tarea que da trabajo y el porqué).',
            ]);
        }

        $familia = Familia::where('nombre', $validated['familia'])->first();
        if (!$familia || !$empresa->familias()->whereKey($familia->id)->exists()) {
            throw ValidationException::withMessages(['familia' => 'Esa familia no está vinculada a la empresa.']);
        }
        $ciclo = CicloFormativo::whereKey($validated['ciclo_id'])->where('familia_id', $familia->id)->first();
        if (!$ciclo) {
            throw ValidationException::withMessages(['ciclo_id' => 'El ciclo no pertenece a esa familia.']);
        }

        $esAmbos = $validated['cursoSeleccionado'] === 'ambos_cursos';
        $peticion = $this->peticionGenerar($comercial, [
            'empresa_id'         => $empresa->id,
            'empresaNombre'      => $empresa->nombre_comercial,
            'empresaSector'      => $empresa->sector ?: 'Sin especificar',
            'empresaTamano'      => $empresa->tamano,
            'empresaUbicacion'   => collect([$empresa->municipio, $empresa->provincia])->filter()->implode(', ') ?: null,
            'diaANormal'         => $empresa->dia_a_normal,
            'friccionArea'       => $empresa->friccion_area,
            'friccionProblema'   => $empresa->friccion_problema,
            'restricciones'      => $empresa->restricciones,
            'loQueNoQuieren'     => $empresa->lo_que_no_quieren,
            'expectativasAlumno' => $empresa->expectativas_alumno,
            // La columna guarda las opciones unidas con ", "; generar() las vuelve a unir igual
            'consecuencias'      => $this->consecuenciasComoLista($empresa->consecuencias),
            'ciclo_nombre'       => $ciclo->nombre,
            'ciclo_id'           => $ciclo->id,
            'nivelGrupo'         => $validated['nivelGrupo'],
            'cursoSeleccionado'  => $validated['cursoSeleccionado'],
            'cantidad'           => $validated['cantidad'],
            'familia'            => $familia->nombre,
            'enfoqueReto'        => $validated['enfoqueReto'] ?? null,
        ]);

        $respuesta = app(MicroretoIAController::class)->generar($peticion);
        $cuerpo = $respuesta->getData(true);
        $retos = $respuesta->getStatusCode() === 200 ? ($cuerpo['microretos'] ?? null) : null;
        if (!is_array($retos) || $retos === []) {
            // generar() ya registra en el log el detalle; al comercial le llega solo su mensaje
            throw ValidationException::withMessages([
                'ia' => is_string($cuerpo['error'] ?? null) ? $cuerpo['error'] : 'No se han podido generar los retos. Inténtalo de nuevo.',
            ]);
        }

        $modulo = $esAmbos ? 'Transversal (1º y 2º)' : "A determinar por IA ({$validated['cursoSeleccionado']}º Curso)";

        return DB::transaction(fn () => collect($retos)->map(function (array $reto) use ($empresa, $comercial, $ciclo, $familia, $validated, $esAmbos, $modulo) {
            $datos = array_intersect_key($reto, array_flip(array_merge(self::CAMPOS_RETO, self::CAMPOS_FIRMA)));
            $datos = $this->limpiar($datos);
            $datos += [
                'ciclo_id'    => $ciclo->id,
                'ciclo'       => $ciclo->nombre,
                'modulo'      => $modulo,
                'multimodulo' => false,
                'curso'       => $esAmbos ? 'ambos_cursos' : (string) $validated['cursoSeleccionado'],
                'nivel_grupo' => $validated['nivelGrupo'],
            ];

            $borrador = new MicroretoBorrador();
            $borrador->forceFill([
                'empresa_id'         => $empresa->id,
                'creado_por_user_id' => $comercial->id,
                'ciclo_id'           => $ciclo->id,
                'familia'            => $familia->nombre,
                'datos'              => $datos,
                'estado'             => MicroretoBorrador::ESTADO_PENDIENTE,
            ])->save();

            return $borrador;
        }));
    }

    /**
     * Publica el borrador en estos centros (tienen que ser centros de la empresa): crea un
     * Microreto en cada uno, igual que si lo hubiera guardado un docente de ese centro.
     *
     * @param int[] $centroIds
     * @return Collection<int, Microreto>
     */
    public function publicar(MicroretoBorrador $borrador, array $centroIds, User $superadmin): Collection
    {
        $empresa = $borrador->empresa;
        if ($borrador->estado !== MicroretoBorrador::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages(['borrador' => 'Este reto ya se ha revisado.']);
        }
        if (!$empresa || $empresa->trashed()) {
            throw ValidationException::withMessages(['borrador' => 'La empresa de este reto ya no existe.']);
        }

        $centrosEmpresa = $empresa->centrosAsignados()->pluck('centro_educativo.id')->map(fn ($id) => (int) $id);
        $ajenos = collect($centroIds)->map(fn ($id) => (int) $id)->diff($centrosEmpresa);
        if ($centrosEmpresa->isEmpty() || $ajenos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'centro_ids' => 'Solo se puede publicar en centros en los que esté colocada la empresa. Colócala antes en Base de datos.',
            ]);
        }

        $datos = $borrador->datos;
        $autorId = (int) $borrador->creado_por_user_id;

        // Firmas emitidas para el comercial: se comprueban con su id y nunca se guardan
        $origenValido = $autorId && $this->raCe->verificarOrigen(
            $datos['ra_ce_origen'] ?? null, $datos['ra_ce_firma'] ?? null, $datos['evaluacion_oficial'] ?? [], $autorId
        );
        $campos = array_intersect_key($datos, array_flip(array_merge(self::CAMPOS_RETO, ['ciclo_id', 'ciclo', 'modulo', 'multimodulo', 'curso', 'nivel_grupo'])));
        $campos['empresa_id']   = $empresa->id;
        $campos['ra_ce_origen'] = $origenValido ? $datos['ra_ce_origen'] : null;
        $campos['es_simulado']  = false;
        if (empty($campos['curso'])) {
            $campos['curso'] = MicroretoFichaService::derivarCurso($campos['ciclo_id'] ?? null, $campos['ciclo'] ?? null, $campos['modulo'] ?? null);
        }

        // Ids ya comprobados contra los centros de la empresa (ids de BD, positivos)
        $centros = $centrosEmpresa->intersect($centroIds)->values();

        return DB::transaction(function () use ($borrador, $centros, $superadmin, $empresa, $datos, $campos, $autorId) {
            $creados = $centros->map(function (int $centroId) use ($empresa, $datos, $campos, $autorId) {
                $reto = new Microreto($campos);
                $reto->forceFill(['centro_id' => $centroId]); // no fillable: lo fija el backend
                $reto->diagnostico_empresa = MicroretoFichaService::copiaDiagnosticoParaGuardar($empresa, $datos, $autorId);
                $reto->save();
                return $reto;
            });

            $borrador->forceFill([
                'estado'               => MicroretoBorrador::ESTADO_PUBLICADO,
                'revisado_por_user_id' => $superadmin->id,
                'revisado_at'          => now(),
            ])->save();

            return $creados;
        });
    }

    public function descartar(MicroretoBorrador $borrador, User $superadmin): void
    {
        if ($borrador->estado !== MicroretoBorrador::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages(['borrador' => 'Este reto ya se ha revisado.']);
        }

        $borrador->forceFill([
            'estado'               => MicroretoBorrador::ESTADO_DESCARTADO,
            'revisado_por_user_id' => $superadmin->id,
            'revisado_at'          => now(),
        ])->save();
    }

    /**
     * Centros de la empresa en los que no se imparte el ciclo del borrador: se avisa al
     * superadmin antes de publicar (no se bloquea).
     *
     * @return Collection<int, string>  nombres de centro
     */
    public function centrosSinElCiclo(MicroretoBorrador $borrador): Collection
    {
        if (!$borrador->ciclo_id || !$borrador->empresa) return collect();

        return $borrador->empresa->centrosAsignados()
            ->whereNotExists(fn ($q) => $q->from('centro_ciclo')
                ->whereColumn('centro_ciclo.centro_id', 'centro_educativo.id')
                ->where('centro_ciclo.ciclo_id', $borrador->ciclo_id))
            ->pluck('centro_educativo.nombre');
    }

    /**
     * Mismo formato que manda el Generador (GenerarMicroretoRequest: hasta 20 de 300 caracteres).
     *
     * @return list<string>
     */
    private function consecuenciasComoLista(?string $texto): array
    {
        $partes = array_filter(array_map('trim', explode(', ', (string) $texto)), fn ($p) => $p !== '');
        return array_slice(array_map(fn ($p) => mb_substr($p, 0, 300), array_values($partes)), 0, 20);
    }

    /**
     * GenerarMicroretoRequest sintética con el comercial como usuario: se valida igual que una
     * petición real (mismo patrón que los comandos demo:*, ver InvocaControladoresReales).
     *
     * @param array<string, mixed> $datos
     */
    private function peticionGenerar(User $usuario, array $datos): GenerarMicroretoRequest
    {
        $request = GenerarMicroretoRequest::create('/', 'POST', array_filter($datos, fn ($v) => $v !== null));
        $request->setUserResolver(fn () => $usuario);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request;
    }

    /**
     * Texto de la IA: sin etiquetas HTML (se muestra en la tablet y luego en la biblioteca).
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function limpiar(array $datos): array
    {
        array_walk_recursive($datos, function (&$valor) {
            if (is_string($valor)) $valor = strip_tags($valor);
        });
        return $datos;
    }
}

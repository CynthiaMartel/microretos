<?php

namespace App\Services;

use App\Mail\SolicitudRetoAutorizadosMail;
use App\Mail\SolicitudRetoMail;
use App\Mail\SolicitudRetoRespondidaMail;
use App\Mail\SolicitudRetoRetosListosMail;
use App\Models\CentroEducativo;
use App\Models\CicloFormativo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Modulo;
use App\Models\ResultadoAprendizaje;
use App\Models\SolicitudReto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Solicitudes de retos de docentes: cuando el docente no tiene tiempo de usar el generador,
 * DuaLab le envía un enlace (app DuaLab, /solicitud-reto/{token}, sin cuenta) y el docente
 * cuenta qué quiere trabajar: familia, ciclo, curso, módulos, RA/CE, su grupo, la sesión en el
 * aula y qué pretende. DuaLab elige la empresa, abre el generador con esas respuestas (datosGenerador)
 * y genera; los retos guardados quedan en la recámara. El aviso «tus retos están listos» es manual
 * (avisar): DuaLab elige qué retos enviar, por si genera varias veces.
 *
 * Del token solo se guarda su huella SHA-256: con la BD no se puede abrir el enlace, así que
 * cada envío (invitación, reenvío, aviso) genera un token nuevo y anula el anterior.
 */
class SolicitudRetoService
{
    // Los correos al docente salen desde info@ por la web: quedan en Enviados y sus respuestas, en
    // Central → «Respuestas de solicitudes» (por la referencia del asunto)
    public function __construct(private CorreoInfoService $correoInfo) {}

    // Campos de EnviarSolicitudRetoRequest que van a la columna JSON `respuestas`. Cada bloque es
    // una petición para el generador: familiaId, cicloId, curso, moduloIds, seleccionRaCe, tipoPropuesta
    public const CAMPOS_RESPUESTA = [
        'bloques', 'nivelGrupo', 'puntoTemario', 'unidadTemario',
        'tareasEmpresa', 'entregable', 'observaciones',
    ];
    public const CAMPOS_BLOQUE = ['familiaId', 'cicloId', 'curso', 'moduloIds', 'seleccionRaCe', 'tipoPropuesta'];

    /**
     * @param array<string, mixed> $validated  StoreSolicitudRetoRequest
     * @return array{0: SolicitudReto, 1: string}  la solicitud y el enlace
     */
    public function crearYEnviar(User $autor, array $validated): array
    {
        // Si el correo no se puede preparar, no queda una solicitud creada sin enviar
        return DB::transaction(function () use ($autor, $validated) {
            $solicitud = new SolicitudReto($validated);
            $solicitud->forceFill([
                'centro_id'          => $validated['centro_id'] ?? null,
                'estado'             => SolicitudReto::ESTADO_PENDIENTE,
                'creada_por_user_id' => $autor->id,
            ])->save();

            return [$solicitud, $this->enviar($solicitud, $autor)];
        });
    }

    /** Enlace nuevo (anula el anterior), se envía por correo al docente y se devuelve para copiarlo */
    public function enviar(SolicitudReto $solicitud, User $autor): string
    {
        if ($solicitud->estado !== SolicitudReto::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages(['solicitud' => 'El docente ya ha enviado esta solicitud o está descartada.']);
        }

        // Enlace nuevo y correo, juntos: si el correo falla, el enlace anterior sigue valiendo
        return DB::transaction(function () use ($solicitud, $autor) {
            $enlace = $this->nuevoEnlace($solicitud, (int) config('solicitudes_reto.dias_validez'));
            $solicitud->forceFill(['enviado_at' => now()])->save();
            $this->correoInfo->enviar(new SolicitudRetoMail($solicitud, $autor, $enlace), $solicitud->email_docente, $autor->name);

            return $enlace;
        });
    }

    /** Tope de remitentes que se pasan a la API del buzón (van en la URL de la petición) */
    public const MAX_REMITENTES_CORREO = 150;

    /**
     * Qué correos de info@ son de las solicitudes: los que llevan la referencia en el asunto o llegan
     * de un docente con una solicitud abierta (no descartada). Para separarlos en Central.
     *
     * @return array{asunto: string, de: array<int, string>}
     */
    public function origenCorreos(): array
    {
        return [
            'asunto' => SolicitudReto::PREFIJO_REFERENCIA,
            'de'     => SolicitudReto::where('estado', '!=', SolicitudReto::ESTADO_DESCARTADA)
                ->latest()->limit(self::MAX_REMITENTES_CORREO)->pluck('email_docente')
                ->map(fn ($e) => strtolower($e))->unique()->values()->all(),
        ];
    }

    /** Solicitud del enlace, o null si no existe; si existe pero ya no vale, enlaceVigente() lo dice */
    public function buscar(string $token): ?SolicitudReto
    {
        if (strlen($token) !== 64) return null;

        return SolicitudReto::where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * Lo que ve el docente al abrir el enlace: su nombre y centro, sus respuestas (para seguir
     * donde lo dejó) y la información de protección de datos; y, si DuaLab ya le avisó, los retos
     * que le envió. Nunca las notas internas, la empresa asignada ni quién la creó.
     *
     * @return array<string, mixed>
     */
    public function datosPublicos(SolicitudReto $solicitud): array
    {
        $privacidad = config('solicitudes_reto.privacidad');

        return [
            'estado'        => $solicitud->estado,
            'nombreDocente' => $solicitud->nombre_docente,
            'centroNombre'  => $solicitud->centro_nombre,
            'respuestas'    => (object) $this->respuestas($solicitud),
            'sesion'        => $this->sesion($solicitud),
            'expira_at'     => $solicitud->expira_at?->toIso8601String(),
            'avisado'       => $solicitud->avisado_at !== null,
            'privacidad'    => ['version' => $privacidad['version'], 'titulo' => $privacidad['titulo'], 'texto' => $privacidad['texto']],
        ];
    }

    /**
     * Guarda lo que el docente lleva rellenado (solo los campos que llegan).
     *
     * @param array<string, mixed> $validated  GuardarSolicitudRetoRequest
     */
    public function guardar(SolicitudReto $solicitud, array $validated): void
    {
        $respuestas = array_intersect_key($validated, array_flip(self::CAMPOS_RESPUESTA));
        $solicitud->fill(array_filter([
            'nombre_docente' => $validated['nombreDocente'] ?? null,
            'centro_nombre'  => $validated['centroNombre'] ?? null,
        ], fn ($v) => filled($v)));
        $solicitud->respuestas = array_merge($this->respuestas($solicitud), $respuestas);

        foreach (['sesionFecha' => 'sesion_fecha', 'sesionHoraInicio' => 'sesion_hora_inicio', 'sesionHoraFin' => 'sesion_hora_fin'] as $campo => $columna) {
            if (array_key_exists($campo, $validated)) $solicitud->{$columna} = $validated[$campo];
        }

        $solicitud->forceFill(['privacidad_version' => $validated['privacidadVersion']])->save();
    }

    /**
     * El docente envía la solicitud: queda respondida (el enlace ya no admite cambios) y se avisa
     * a quien la creó en DuaLab.
     *
     * @param array<string, mixed> $validated  EnviarSolicitudRetoRequest
     */
    public function recibir(SolicitudReto $solicitud, array $validated): void
    {
        DB::transaction(function () use ($solicitud, $validated) {
            // Bloquea la fila: si se pulsa «Enviar» dos veces, la segunda ya no la encuentra pendiente
            $bloqueada = SolicitudReto::whereKey($solicitud->id)->lockForUpdate()->first();
            if (!$bloqueada || !$bloqueada->formularioAbierto()) {
                throw ValidationException::withMessages(['solicitud' => 'Esta solicitud ya se ha enviado.']);
            }

            $this->guardar($bloqueada, $validated);
            $bloqueada->forceFill(['estado' => SolicitudReto::ESTADO_RESPONDIDA, 'respondido_at' => now()])->save();
            $solicitud->setRawAttributes($bloqueada->getAttributes(), true);
        });

        $autor = $solicitud->creadaPor;
        if ($autor?->email && !$autor->is_blocked) {
            Mail::to($autor->email)->queue(new SolicitudRetoRespondidaMail($solicitud, $autor->name));
        }
    }

    /**
     * DuaLab asigna empresa o centro, anota, o descarta / recupera la solicitud.
     *
     * @param array<string, mixed> $validated  UpdateSolicitudRetoRequest
     */
    public function actualizar(SolicitudReto $solicitud, array $validated): void
    {
        if (array_key_exists('notas', $validated)) $solicitud->notas = $validated['notas'];

        $asignado = array_intersect_key($validated, array_flip(['empresa_id', 'centro_id']));
        // Empresa de una petición concreta (solicitudes con varias familias o ciclos)
        if (array_key_exists('bloque', $validated) && array_key_exists('empresa_id', $validated)) {
            $porPeticion = $solicitud->empresas_peticiones ?? [];
            if ($validated['empresa_id']) $porPeticion[(string) $validated['bloque']] = (int) $validated['empresa_id'];
            else unset($porPeticion[(string) $validated['bloque']]);
            unset($asignado['empresa_id']);
            $asignado['empresas_peticiones'] = $porPeticion ?: null;
        }
        if (array_key_exists('descartada', $validated)) {
            $asignado['estado'] = match (true) {
                (bool) $validated['descartada']     => SolicitudReto::ESTADO_DESCARTADA,
                $solicitud->respondido_at !== null  => SolicitudReto::ESTADO_RESPONDIDA,
                default                             => SolicitudReto::ESTADO_PENDIENTE,
            };
        }

        $solicitud->forceFill($asignado)->save();
    }

    /**
     * Aviso manual «tus retos están listos»: DuaLab elige qué retos de la solicitud enviar. El
     * correo lleva un enlace nuevo (el anterior deja de valer) que muestra esos retos en solo lectura.
     *
     * @param array<int, string> $uuids
     */
    public function avisar(SolicitudReto $solicitud, array $uuids, ?string $mensaje, User $autor): void
    {
        if ($solicitud->estado !== SolicitudReto::ESTADO_RESPONDIDA) {
            throw ValidationException::withMessages(['retos' => 'Solo se puede avisar de una solicitud que el docente ha enviado.']);
        }

        $retos = $solicitud->microretos()->whereIn('uuid', $uuids)->get(['id', 'uuid', 'titulo']);
        if ($retos->count() !== count($uuids)) {
            throw ValidationException::withMessages(['retos' => 'Algún reto no es de esta solicitud o ya no existe.']);
        }

        DB::transaction(function () use ($solicitud, $autor, $retos, $uuids, $mensaje) {
            $enlace = $this->nuevoEnlace($solicitud, (int) config('solicitudes_reto.dias_validez_retos'));
            $solicitud->forceFill(['avisado_at' => now(), 'retos_avisados' => array_values($uuids)])->save();
            $this->correoInfo->enviar(new SolicitudRetoRetosListosMail(
                $solicitud, $autor, $enlace, $retos->pluck('titulo')->filter()->values()->all(), $mensaje,
            ), $solicitud->email_docente, $autor->name);
        });
    }

    /**
     * Fichas de retos de la solicitud, como en la biblioteca: las del enlace del aviso y la vista
     * previa de Central. Los uuid que no son de esta solicitud se ignoran.
     *
     * @param array<int, string> $uuids
     */
    public function fichasRetos(SolicitudReto $solicitud, array $uuids): Collection
    {
        $retos = $solicitud->microretos()
            ->with(['empresa.centroEducativo', 'empresa.familias'])
            ->whereIn('uuid', $uuids)
            ->get();

        return MicroretoFichaService::enriquecerLote($retos);
    }

    /**
     * Las respuestas con los bloques normalizados. Las solicitudes anteriores a los bloques (noviembre
     * de 2026) guardaban un solo bloque suelto, con `objetivo` en lugar de `tipoPropuesta`.
     *
     * @return array<string, mixed>
     */
    public function respuestas(SolicitudReto $solicitud): array
    {
        $r = $solicitud->respuestas ?? [];
        if (!isset($r['bloques']) && isset($r['familiaId'])) {
            $r['bloques'] = [array_intersect_key($r, array_flip(self::CAMPOS_BLOQUE)) + ['tipoPropuesta' => $r['objetivo'] ?? null]];
            $r = array_diff_key($r, array_flip(self::CAMPOS_BLOQUE));
        }

        return $r;
    }

    /** @return array<int, array<string, mixed>> */
    public function bloques(SolicitudReto $solicitud): array
    {
        return array_values(array_filter($this->respuestas($solicitud)['bloques'] ?? [], 'is_array'));
    }

    /**
     * Cada bloque con los nombres del currículo, para leerlo en Central y elegirlo en el generador.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function curriculo(SolicitudReto $solicitud): Collection
    {
        $bloques = collect($this->bloques($solicitud));
        $empresas = Empresa::whereIn('id', $bloques->keys()->map(fn ($i) => $this->empresaDe($solicitud, $i))->filter())
            ->pluck('nombre_comercial', 'id');

        return $bloques->map(fn ($b, $i) => $this->curriculoBloque($b) + [
            'indice'  => $i,
            'empresa' => ($id = $this->empresaDe($solicitud, $i)) ? ['id' => $id, 'nombre' => $empresas[$id] ?? null] : null,
        ]);
    }

    /** Empresa de una petición: la que DuaLab eligió para ella o, si no, la de toda la solicitud */
    public function empresaDe(SolicitudReto $solicitud, int $indice): ?int
    {
        $id = ($solicitud->empresas_peticiones ?? [])[(string) $indice] ?? $solicitud->empresa_id;

        return $id ? (int) $id : null;
    }

    /**
     * Centro con el que se abre el generador (su paso 1 no deja buscar empresas sin centro): el de la
     * empresa elegida, el del docente si está dado de alta o, si no, el de la beta (config).
     */
    public function centroGenerador(SolicitudReto $solicitud, int $indice): ?string
    {
        $empresa = ($id = $this->empresaDe($solicitud, $indice)) ? Empresa::with('centroEducativo:id,nombre', 'centrosAsignados:id,nombre')->find($id) : null;
        if ($empresa && ($centro = $this->centroDeEmpresa($empresa))) return $centro;
        if ($solicitud->centro_id) return CentroEducativo::whereKey($solicitud->centro_id)->value('nombre');

        return CentroEducativo::where('nombre', config('solicitudes_reto.centro_generador'))->value('nombre');
    }

    /**
     * Empresas que encajan con una petición: las que tienen su familia, primero las del centro con el
     * que se abrirá el generador. Para elegir la empresa de cada petición en Central.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function empresasParaPeticion(SolicitudReto $solicitud, int $indice, bool $todas = false): Collection
    {
        // Por defecto, las de la familia de la petición; con $todas, cualquier familia (el buscador de la
        // ficha filtra después por familia, sector y tipo, como el del generador)
        $familiaId = $this->bloques($solicitud)[$indice]['familiaId'] ?? null;
        if (!$familiaId && !$todas) return collect();
        $centro = $solicitud->centro_id
            ? CentroEducativo::whereKey($solicitud->centro_id)->value('nombre')
            : config('solicitudes_reto.centro_generador');

        return Empresa::with('centroEducativo:id,nombre', 'centrosAsignados:id,nombre', 'familias:familias.id,familias.nombre')
            ->when(!$todas, fn ($q) => $q->whereHas('familias', fn ($f) => $f->where('familias.id', $familiaId)))
            ->orderBy('nombre_comercial')->limit($todas ? 500 : 200)
            ->get(['id', 'nombre_comercial', 'sector', 'es_simulada', 'centro_id', 'centro_educativo'])
            ->map(fn (Empresa $e) => [
                'id'          => $e->id,
                'nombre'      => $e->nombre_comercial,
                'sector'      => $e->sector,
                'es_simulada' => (bool) $e->es_simulada,
                'familias'    => $e->familias->pluck('nombre')->values(),
                'centro'      => $this->centroDeEmpresa($e),
            ])
            // Sin centro (plantillas del catálogo) el generador no puede usarla sin copiarla antes
            ->filter(fn ($e) => $e['centro'] !== null)
            ->sortBy(fn ($e) => $e['centro'] === $centro ? 0 : 1)->values();
    }

    /** Centro de una empresa: el suyo o, si es captada (centro_empresa), el primero donde está */
    private function centroDeEmpresa(Empresa $empresa): ?string
    {
        return $empresa->centroEducativo?->nombre ?? $empresa->centro_educativo ?? $empresa->centrosAsignados->first()?->nombre;
    }

    /**
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function curriculoBloque(array $b): array
    {
        $ids = $b['moduloIds'] ?? [];
        $modulos = $ids ? Modulo::whereIn('id', $ids)->orderBy('curso')->orderBy('nombre')->get(['id', 'nombre', 'curso']) : collect();
        $seleccion = collect($b['seleccionRaCe'] ?? []);
        $ras = $seleccion->isEmpty() ? collect() : ResultadoAprendizaje::with('criteriosEvaluacion')
            ->whereIn('id', $seleccion->pluck('ra_id'))->get()->keyBy('id');

        return [
            'familia'       => isset($b['familiaId']) ? Familia::whereKey($b['familiaId'])->value('nombre') : null,
            'ciclo'         => isset($b['cicloId']) ? CicloFormativo::whereKey($b['cicloId'])->value('nombre') : null,
            'curso'         => $b['curso'] ?? null,
            'modulos'       => $modulos->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'curso' => $m->curso])->values(),
            'tipoPropuesta' => $b['tipoPropuesta'] ?? null,
            'ra_ce'         => $seleccion->map(function ($s) use ($ras) {
                $ra = $ras->get((int) $s['ra_id']);
                $ceIds = array_map('intval', $s['ce_ids'] ?? []);

                return [
                    'ra_id'     => (int) $s['ra_id'],
                    'modulo_id' => $ra?->idmodulo,
                    'ce_ids'    => $ceIds,
                    'ra'        => $ra?->ra,
                    'ce'        => $ra ? $ra->criteriosEvaluacion->whereIn('id', $ceIds)->pluck('ce')->values() : [],
                ];
            })->values(),
        ];
    }

    /**
     * Un bloque con los nombres del estado del generador (GeneradorMicroretos.vue, `seleccion`). El
     * enfoqueReto es lo que el docente contestó a «¿Qué tipo de propuesta quieres trabajar?», con el
     * contexto común detrás (borradorEnfoque); DuaLab lo revisa antes de generar.
     *
     * @return array<string, mixed>|null  null si no existe ese bloque
     */
    public function datosGenerador(SolicitudReto $solicitud, int $indice = 0): ?array
    {
        $bloques = $this->bloques($solicitud);
        if (!isset($bloques[$indice])) return null;

        $r = $this->respuestas($solicitud);
        $c = $this->curriculoBloque($bloques[$indice]);

        return [
            'solicitud_id'      => $solicitud->id,
            'bloque'            => $indice,
            'total_bloques'     => count($bloques),
            'docente'           => $solicitud->nombre_docente,
            'centro'            => $solicitud->centro_nombre,
            'empresa_id'        => $this->empresaDe($solicitud, $indice),
            // Para su paso 1: centro y filtro de familia, para que la lista de empresas ya encaje
            'centro_generador'  => $this->centroGenerador($solicitud, $indice),
            'familia'           => $c['familia'],
            'cicloId'           => $bloques[$indice]['cicloId'] ?? null,
            'cursoSeleccionado' => $bloques[$indice]['curso'] ?? null,
            'moduloIds'         => $c['modulos']->pluck('id')->all(),
            'seleccionRaCe'     => $c['ra_ce']->map(fn ($s) => array_intersect_key($s, array_flip(['ra_id', 'modulo_id', 'ce_ids'])))->all(),
            // Sin nivel, lo decide DuaLab en el generador
            'nivelGrupo'        => $r['nivelGrupo'] ?? null,
            // El diagnóstico (paso 2) es el de la empresa: lo del docente va en el paso 3
            'enfoqueReto'       => $this->borradorEnfoque($solicitud, $indice),
            'tareasPracticas'   => $r['tareasEmpresa'] ?? null,
            'entregableDocente' => $r['entregable'] ?? null,
        ];
    }

    /**
     * Lo que necesita la «generación rápida» de Central (tarjeta de cada petición): la petición para
     * POST /generar-microreto (mismos campos que manda el generador de la app) con la empresa elegida y
     * su diagnóstico, y el contexto que el generador guarda con cada reto. `faltan` dice qué impide
     * generar (sin empresa, o empresa sin diagnóstico): entonces hay que completarlo antes.
     *
     * @return array<string, mixed>|null  null si no existe esa petición
     */
    public function datosGeneracion(SolicitudReto $solicitud, int $indice): ?array
    {
        $bloques = $this->bloques($solicitud);
        if (!isset($bloques[$indice])) return null;

        $b = $bloques[$indice];
        $r = $this->respuestas($solicitud);
        $c = $this->curriculoBloque($b);
        $ciclo = isset($b['cicloId']) ? CicloFormativo::find($b['cicloId']) : null;
        $empresa = ($id = $this->empresaDe($solicitud, $indice)) ? Empresa::find($id) : null;
        $curso = (string) ($b['curso'] ?? '');
        $moduloIds = $c['modulos']->pluck('id')->all();

        $faltan = [];
        if (!$empresa) $faltan[] = 'empresa';
        elseif (blank($empresa->friccion_problema)) $faltan[] = 'diagnostico';

        // Mismo texto de módulo que guarda el generador (GeneradorMicroretos.vue, generarReto)
        $nombres = $c['modulos']->pluck('nombre')->all();
        $modulo = $curso === 'ambos_cursos'
            ? ($nombres ? implode(' y ', $nombres) . ' (Transversal 1º y 2º)' : 'Transversal (1º y 2º)')
            : ($nombres ? implode(' y ', $nombres) : "A determinar por IA ({$curso}º Curso)");

        return [
            'faltan'  => $faltan,
            'empresa' => $empresa ? [
                'id' => $empresa->id, 'nombre' => $empresa->nombre_comercial, 'sector' => $empresa->sector,
                'es_simulada' => (bool) $empresa->es_simulada, 'friccion_problema' => $empresa->friccion_problema,
            ] : null,
            // Cuerpo de POST /generar-microreto: lo que el docente puede ajustar en Central va aparte (editables)
            'peticion' => [
                'empresa_id'        => $empresa?->id,
                'empresaNombre'     => $empresa?->nombre_comercial,
                'empresaSector'     => $empresa?->sector,
                'empresaTamano'     => $empresa?->tamano,
                'empresaUbicacion'  => $empresa ? (implode(', ', array_filter([$empresa->municipio, $empresa->provincia])) ?: null) : null,
                'diaANormal'        => $empresa?->dia_a_normal,
                'friccionArea'      => $empresa?->friccion_area,
                'friccionProblema'  => $empresa?->friccion_problema,
                'restricciones'     => $empresa?->restricciones,
                'loQueNoQuieren'    => $empresa?->lo_que_no_quieren,
                'expectativasAlumno' => $empresa?->expectativas_alumno,
                'consecuencias'     => $empresa && filled($empresa->consecuencias)
                    ? array_values(array_filter(array_map('trim', explode(',', $empresa->consecuencias)))) : [],
                'ciclo_nombre'      => $ciclo?->nombre,
                'ciclo_id'          => $ciclo?->id,
                'familia'           => $c['familia'],
                'cursoSeleccionado' => $curso,
                'modulo_id'         => $moduloIds ?: null,
                'seleccion_ra_ce'   => $moduloIds ? $c['ra_ce']->map(fn ($x) => ['ra_id' => $x['ra_id'], 'ce_ids' => $x['ce_ids']])->values()->all() : null,
            ],
            'editables' => [
                'nivelGrupo'        => $r['nivelGrupo'] ?? 'Medio',
                'enfoqueReto'       => $this->borradorEnfoque($solicitud, $indice),
                'tareasPracticas'   => $r['tareasEmpresa'] ?? '',
                'entregableDocente' => $r['entregable'] ?? '',
                'cantidad'          => 3,
            ],
            // Lo que el generador añade a cada reto al guardarlo (snapshot del contexto)
            'contexto' => [
                'empresa_id'          => $empresa?->id,
                'ciclo_id'            => $ciclo?->id,
                'ciclo'               => $ciclo?->nombre,
                'modulo'              => $modulo,
                'multimodulo'         => $curso !== 'ambos_cursos' && count($moduloIds) !== 1,
                'curso'               => $curso,
                'es_simulado'         => (bool) $empresa?->es_simulada,
                'empresa_es_simulada' => (bool) $empresa?->es_simulada,
            ],
        ];
    }

    /**
     * Pasa retos de la recámara de esta solicitud a la biblioteca de un centro (como publicar un reto en
     * borrador de una captación): dejan de estar en la recámara y los ven los docentes de ese centro.
     *
     * @param array<int, string> $uuids
     */
    public function publicarRetos(SolicitudReto $solicitud, array $uuids, int $centroId): int
    {
        $retos = $solicitud->microretos()->whereIn('uuid', $uuids)->where('en_recamara', true)->get();
        if ($retos->count() !== count($uuids)) {
            throw ValidationException::withMessages(['retos' => 'Algún reto no es de esta solicitud o ya está publicado.']);
        }

        DB::transaction(function () use ($retos, $centroId) {
            // centro_id y en_recamara no son fillable: los fija el backend
            $retos->each(fn ($m) => $m->forceFill(['en_recamara' => false, 'centro_id' => $centroId])->save());
        });

        return $retos->count();
    }

    /**
     * Cuenta de DuaLab del docente de la solicitud: docente o admin de centro, con su correo, con centro
     * y sin bloquear. Sin ella solo puede leer los retos (avisar); con ella se le pueden autorizar.
     */
    public function cuentaDocente(SolicitudReto $solicitud): ?User
    {
        if (blank($solicitud->email_docente)) return null;

        return User::with('centroEducativo:id,nombre')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($solicitud->email_docente))])
            ->whereIn('role', [User::ROLE_DOCENTE, User::ROLE_ADMIN])
            ->whereNotNull('centro_educativo_id')
            ->where('is_blocked', false)
            ->first();
    }

    /**
     * «Autorizar a trabajarlos»: los retos pasan a la biblioteca del centro de la cuenta del docente
     * (si estaban en la recámara) y le llega un correo con el enlace a cada uno en la app, donde
     * puede pulsar «Trabajar este reto». Solo si tiene cuenta (cuentaDocente).
     *
     * @param array<int, string> $uuids
     */
    public function autorizar(SolicitudReto $solicitud, array $uuids, ?string $mensaje, User $autor): User
    {
        if ($solicitud->estado !== SolicitudReto::ESTADO_RESPONDIDA) {
            throw ValidationException::withMessages(['retos' => 'Solo se puede autorizar en una solicitud que el docente ha enviado.']);
        }
        $cuenta = $this->cuentaDocente($solicitud);
        if (!$cuenta) {
            throw ValidationException::withMessages(['retos' => 'No hay ninguna cuenta de DuaLab con el correo del docente: de momento solo puede leerlos («Avisar al docente»).']);
        }

        $retos = $solicitud->microretos()->whereIn('uuid', $uuids)->get(['id', 'uuid', 'titulo', 'en_recamara', 'centro_id']);
        if ($retos->count() !== count($uuids)) {
            throw ValidationException::withMessages(['retos' => 'Algún reto no es de esta solicitud o ya no existe.']);
        }
        // Uno ya publicado en otro centro no se mueve: lo ven sus docentes y quizá ya lo trabajan
        if ($retos->contains(fn ($m) => !$m->en_recamara && $m->centro_id !== $cuenta->centro_educativo_id)) {
            throw ValidationException::withMessages(['retos' => 'Algún reto ya está en la biblioteca de otro centro: desmárcalo.']);
        }

        DB::transaction(function () use ($retos, $cuenta, $solicitud, $autor, $mensaje) {
            // centro_id y en_recamara no son fillable: los fija el backend
            $retos->each(fn ($m) => $m->forceFill(['en_recamara' => false, 'centro_id' => $cuenta->centro_educativo_id])->save());
            $url = rtrim(config('solicitudes_reto.url_app'), '/');
            $this->correoInfo->enviar(new SolicitudRetoAutorizadosMail(
                $solicitud, $autor,
                $retos->map(fn ($m) => ['titulo' => $m->titulo ?: 'Reto sin título', 'enlace' => "{$url}/retos/{$m->uuid}"])->values()->all(),
                $mensaje,
            ), $solicitud->email_docente, $autor->name);
        });

        return $cuenta;
    }

    /**
     * Solicitudes que se pueden llevar al generador (respondidas), con sus bloques resumidos: el
     * desplegable «Solicitudes del profesorado» del generador.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function paraGenerador(): Collection
    {
        return SolicitudReto::where('estado', SolicitudReto::ESTADO_RESPONDIDA)
            ->withCount('microretos')->latest('respondido_at')->limit(50)->get()
            ->map(fn (SolicitudReto $s) => [
                'id'            => $s->id,
                'docente'       => $s->nombre_docente,
                'centro'        => $s->centro_nombre,
                'respondido_at' => $s->respondido_at?->toIso8601String(),
                'num_retos'     => $s->microretos_count,
                'bloques'       => $this->curriculo($s)->map(fn ($b) => [
                    'indice'  => $b['indice'],
                    'familia' => $b['familia'],
                    'ciclo'   => $b['ciclo'],
                    'curso'   => $b['curso'],
                    'modulos' => $b['modulos']->pluck('nombre')->all(),
                ])->all(),
            ]);
    }

    /**
     * Texto para el paso 3 del generador (enfoqueReto, hasta 1000 caracteres): el tipo de propuesta del
     * bloque y, detrás, el contexto común que el generador no pregunta (temario y sesión). Las tareas en
     * la empresa y el entregable tienen campo propio en el generador (datosGenerador).
     */
    public function borradorEnfoque(SolicitudReto $solicitud, int $indice = 0): string
    {
        $r = $this->respuestas($solicitud);
        $b = $this->bloques($solicitud)[$indice] ?? [];
        $unidad = filled($r['unidadTemario'] ?? null) ? ' (' . trim($r['unidadTemario']) . ')' : '';

        $partes = [
            filled($b['tipoPropuesta'] ?? null) ? rtrim(trim($b['tipoPropuesta']), '.') . '.' : null,
            match ($r['puntoTemario'] ?? null) {
                'primera_vez' => "El alumnado ve este contenido por primera vez{$unidad}.",
                'en_curso'    => "El alumnado está trabajando ahora este contenido{$unidad}.",
                'repaso'      => "El alumnado ya ha visto este contenido{$unidad}: es un repaso o una ampliación.",
                default       => $unidad ? 'Contenido: ' . trim($r['unidadTemario']) . '.' : null,
            },
            ($duracion = $this->duracion($solicitud)) ? "Se hará en una sesión de {$duracion}." : null,
        ];

        return Str::limit(implode(' ', array_filter($partes)), 1000, '');
    }

    /** @return array{fecha: ?string, hora_inicio: ?string, hora_fin: ?string, duracion: ?string} */
    public function sesion(SolicitudReto $solicitud): array
    {
        return [
            'fecha'       => $solicitud->sesion_fecha?->format('Y-m-d'),
            'hora_inicio' => $solicitud->sesion_hora_inicio ? substr($solicitud->sesion_hora_inicio, 0, 5) : null,
            'hora_fin'    => $solicitud->sesion_hora_fin ? substr($solicitud->sesion_hora_fin, 0, 5) : null,
            'duracion'    => $this->duracion($solicitud),
        ];
    }

    /** «1 h 50 min», o null sin horario */
    private function duracion(SolicitudReto $solicitud): ?string
    {
        if (!$solicitud->sesion_hora_inicio || !$solicitud->sesion_hora_fin) return null;

        [$hi, $mi] = array_map('intval', explode(':', $solicitud->sesion_hora_inicio));
        [$hf, $mf] = array_map('intval', explode(':', $solicitud->sesion_hora_fin));
        $minutos = ($hf * 60 + $mf) - ($hi * 60 + $mi);
        if ($minutos <= 0) return null;

        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return trim(($h ? "{$h} h " : '') . ($m ? "{$m} min" : ''));
    }

    private function nuevoEnlace(SolicitudReto $solicitud, int $dias): string
    {
        $token = Str::random(64);
        $solicitud->forceFill([
            'token_hash' => hash('sha256', $token),
            'expira_at'  => now()->addDays($dias),
        ])->save();

        return rtrim((string) config('solicitudes_reto.url_app'), '/') . '/solicitud-reto/' . $token;
    }
}

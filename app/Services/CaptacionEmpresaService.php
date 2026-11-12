<?php

namespace App\Services;

use App\Models\CaptacionOrigen;
use App\Models\Empresa;
use App\Models\EmpresaCaptacion;
use App\Models\EmpresaConsentimiento;
use App\Models\EmpresaFirma;
use App\Models\MicroretoBorrador;
use App\Models\User;
use App\Notifications\EmpresaCaptadaPendienteValidacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Empresas captadas (empresa_captaciones). Por ahora llegan por la entrevista del comercial
 * (app comercial.dualab.es): alta → diagnóstico → firma de consentimientos; después DuaLab
 * la valida y entonces se crea la empresa de verdad en `empresas`, colocada en sus centros.
 *
 * Estado (no fillable, solo lo cambia este servicio):
 *   borrador ──firmar()──▶ pendiente_validacion ──validar()──▶ validada
 *                                              └─descartar()─▶ descartada
 * Hasta validarla no existe en `empresas`: ningún centro la ve ni la usa.
 */
class CaptacionEmpresaService
{
    // Campos camelCase de StoreEmpresaComercialRequest → columnas (las mismas en empresa_captaciones y empresas)
    private const CAMPOS_EMPRESA = [
        'nombreComercial'     => 'nombre_comercial',
        'razonSocial'         => 'razon_social',
        'cif'                 => 'cif',
        'sector'              => 'sector',
        'tamano'              => 'tamano',
        'nivelDigitalizacion' => 'nivel_digitalizacion',
        'web'                 => 'web',
        'actividad'           => 'actividad',
        'personaContacto'     => 'persona_contacto',
        'telefono'            => 'telefono',
        'emailGeneral'        => 'email_general',
        'direccion'           => 'direccion',
        'municipio'           => 'municipio',
        'provincia'           => 'provincia',
        'codigoPostal'        => 'codigo_postal',
    ];

    private const CAMPOS_DIAGNOSTICO = [
        'diaANormal'         => 'dia_a_normal',
        'friccionArea'       => 'friccion_area',
        'friccionProblema'   => 'friccion_problema',
        'consecuencias'      => 'consecuencias',
        'restricciones'      => 'restricciones',
        'loQueNoQuieren'     => 'lo_que_no_quieren',
        'expectativasAlumno' => 'expectativas_alumno',
    ];

    // Mínimo de diagnóstico para firmar o generar retos (= obligatorios de UpdateDiagnosticoEmpresaRequest)
    private const DIAGNOSTICO_MINIMO = ['dia_a_normal', 'friccion_area', 'friccion_problema'];

    // Datos opcionales en la entrevista que DuaLab necesitará después (contactar, facturar, ubicar):
    // si faltan, se avisa al superadmin para completarlos al validarla.
    public const DATOS_RECOMENDADOS = [
        'cif'              => 'CIF',
        'razon_social'     => 'razón social',
        'persona_contacto' => 'persona de contacto',
        'telefono'         => 'teléfono',
        'email_general'    => 'email',
        'municipio'        => 'municipio',
        'tamano'           => 'tamaño',
        'web'              => 'web',
    ];

    public function __construct(private EmpresaFamiliaService $familias) {}

    /** @param array<string, mixed> $validated  StoreEmpresaComercialRequest */
    public function crear(User $comercial, array $validated): EmpresaCaptacion
    {
        // Alta sin conexión que se reenvía al sincronizar: si la primera petición llegó pero la
        // respuesta se perdió, se devuelve la captación ya creada en vez de duplicarla
        if (!empty($validated['clienteUuid'])) {
            $existente = EmpresaCaptacion::where('cliente_uuid', $validated['clienteUuid'])
                ->where('creada_por_user_id', $comercial->id)->first();
            if ($existente) return $existente;
        }

        $captacion = new EmpresaCaptacion($this->columnas($validated, self::CAMPOS_EMPRESA));
        $captacion->familias = $this->familiasConInteres($validated['familias'], $validated['interesesFamilia'] ?? [], []);
        $captacion->forceFill([
            'origen_id'          => CaptacionOrigen::ENTREVISTA,
            'cliente_uuid'       => $validated['clienteUuid'] ?? null,
            'creada_por_user_id' => $comercial->id,
            'estado'             => EmpresaCaptacion::ESTADO_BORRADOR,
        ])->save();

        return $captacion;
    }

    /** @param array<string, mixed> $validated  UpdateEmpresaComercialRequest */
    public function actualizar(EmpresaCaptacion $captacion, array $validated): EmpresaCaptacion
    {
        $captacion->fill($this->columnas($validated, self::CAMPOS_EMPRESA));
        $captacion->familias = $this->familiasConInteres(
            $validated['familias'] ?? $captacion->nombresFamilias(),
            $validated['interesesFamilia'] ?? [],
            $captacion->familias ?? [],
        );
        $captacion->save();

        return $captacion;
    }

    /** @param array<string, mixed> $validated  UpdateDiagnosticoEmpresaRequest */
    public function actualizarDiagnostico(EmpresaCaptacion $captacion, array $validated): EmpresaCaptacion
    {
        $captacion->update($this->columnas($validated, self::CAMPOS_DIAGNOSTICO));
        return $captacion;
    }

    /** @return list<string>  nombres legibles de los datos recomendados que faltan */
    public static function datosIncompletos(EmpresaCaptacion $captacion): array
    {
        $faltan = [];
        foreach (self::DATOS_RECOMENDADOS as $columna => $nombre) {
            if (trim((string) $captacion->{$columna}) === '') $faltan[] = $nombre;
        }
        return $faltan;
    }

    public function tieneDiagnosticoMinimo(EmpresaCaptacion $captacion): bool
    {
        foreach (self::DIAGNOSTICO_MINIMO as $columna) {
            if (trim((string) $captacion->{$columna}) === '') return false;
        }
        return true;
    }

    /**
     * Textos legales vigentes tal cual se muestran en la tablet, con la versión que la
     * tablet devuelve al firmar (FirmarEmpresaComercialRequest).
     *
     * @return array{revisados: bool, textos: list<array<string, mixed>>, cesiones: list<array<string, mixed>>}
     */
    public function textosVigentes(): array
    {
        $textos = [];
        foreach (config('captacion.textos') as $clave => $t) {
            $textos[] = ['clave' => $clave, 'version' => $t['version'], 'titulo' => $t['titulo'], 'texto' => $t['texto']];
        }
        $cesiones = [];
        foreach (config('captacion.cesiones') as $clave => $c) {
            $cesiones[] = ['clave' => $clave, 'version' => $c['version'], 'cesionaria' => $c['cesionaria'], 'texto' => $c['texto']];
        }

        return ['revisados' => (bool) config('captacion.textos_revisados'), 'textos' => $textos, 'cesiones' => $cesiones];
    }

    /**
     * Registra la firma y los consentimientos y deja la captación pendiente de validar.
     *
     * @param array<string, mixed> $validated  FirmarEmpresaComercialRequest
     */
    public function firmar(EmpresaCaptacion $captacion, User $comercial, array $validated, ?string $ip, ?string $userAgent): EmpresaFirma
    {
        // Con los textos de ejemplo no se recogen firmas reales (ver config/captacion.php)
        if (app()->isProduction() && !config('captacion.textos_revisados')) {
            throw ValidationException::withMessages([
                'versiones' => 'Los textos legales aún no están revisados: no se puede firmar todavía.',
            ]);
        }
        if (!$this->tieneDiagnosticoMinimo($captacion)) {
            throw ValidationException::withMessages([
                'empresa' => 'Completa la entrevista (actividad, tarea que da trabajo y el porqué) antes de firmar.',
            ]);
        }

        $png = $this->decodificarFirma($validated['firma']);
        $uuid = (string) Str::uuid();
        $ruta = "captacion/firmas/{$uuid}.png";

        $consentimientos = $this->consentimientos($validated);
        $ahora = now();
        $snapshot = [
            'empresa'      => $this->datosFirmados($captacion),
            'firmante'     => [
                'nombre'     => $validated['firmanteNombre'],
                'cargo'      => $validated['firmanteCargo'] ?? null,
                // El DNI va cifrado en su columna; aquí solo su huella, para no dejarlo en claro
                'dni_sha256' => hash('sha256', $validated['firmanteDni']),
            ],
            'consentimientos' => $consentimientos,
            'firma_sha256'    => hash('sha256', $png),
            'comercial_id'    => $comercial->id,
            'firmado_at'      => $ahora->toIso8601String(),
            // Firmada sin conexión: firmado_at es cuando el servidor la recibe y la sella; la
            // hora en que se firmó en la tablet queda aquí, tal como la dio el dispositivo
            'capturada_en_dispositivo_at' => $validated['capturadaAt'] ?? null,
        ];

        // Si algo falla tras escribir el PNG, se borra: nunca queda una firma huérfana en disco
        Storage::disk('local')->put($ruta, $png);
        try {
            $firma = DB::transaction(function () use ($captacion, $comercial, $validated, $uuid, $ruta, $snapshot, $consentimientos, $ahora, $ip, $userAgent) {
                // Doble toque en «Firmar»: la segunda petición espera aquí y ya no la encuentra en borrador
                if (!EmpresaCaptacion::whereKey($captacion->id)->lockForUpdate()->first()?->esBorrador()) {
                    throw ValidationException::withMessages(['firma' => 'Esta entrevista ya está firmada.']);
                }

                $firma = new EmpresaFirma();
                $firma->forceFill([
                    'uuid'              => $uuid,
                    'empresa_captacion_id' => $captacion->id,
                    'comercial_user_id' => $comercial->id,
                    'firmante_nombre'   => $validated['firmanteNombre'],
                    'firmante_dni'      => $validated['firmanteDni'],
                    'firmante_cargo'    => $validated['firmanteCargo'] ?? null,
                    'firmante_email'    => $validated['firmanteEmail'] ?? null,
                    'firma_path'        => $ruta,
                    'snapshot'          => $snapshot,
                    'documento_sha256'  => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                    'ip'                => $ip,
                    'user_agent'        => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                    'firmado_at'        => $ahora,
                ])->save();

                foreach ($consentimientos as $c) {
                    (new EmpresaConsentimiento())->forceFill([
                        'empresa_firma_id' => $firma->id,
                        'clave'            => $c['clave'],
                        'version'          => $c['version'],
                        'texto_sha256'     => $c['texto_sha256'],
                        'aceptado'         => $c['aceptado'],
                    ])->save();
                }

                $captacion->forceFill([
                    'estado'     => EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION,
                    'firmada_at' => $ahora,
                ])->save();

                return $firma;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($ruta);
            throw $e;
        }

        // Aviso in-app a DuaLab: hay una empresa nueva que validar
        Notification::send(
            User::where('role', User::ROLE_SUPERADMIN)->where('is_blocked', false)->get(),
            new EmpresaCaptadaPendienteValidacion($captacion, $comercial)
        );

        return $firma;
    }

    /**
     * Valida la captación y la coloca en estos centros (tabla centro_empresa). A partir de aquí
     * la ven los docentes de esos centros como una empresa real más.
     *  - Sin $empresaExistenteId: crea la empresa en `empresas` (real, sin centro propio) con sus
     *    datos, familias y diagnóstico.
     *  - Con $empresaExistenteId (la misma empresa ya estaba en Base de datos): no se duplica; se
     *    completa lo que le falte sin pisar lo que ya tiene y se añaden familias y centros.
     * Si la captación ya estaba validada, solo cambia los centros en los que está colocada.
     *
     * @param int[] $centroIds
     */
    public function validar(EmpresaCaptacion $captacion, array $centroIds, User $superadmin, ?int $empresaExistenteId = null): Empresa
    {
        if (!in_array($captacion->estado, [EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION, EmpresaCaptacion::ESTADO_VALIDADA], true)) {
            throw ValidationException::withMessages([
                'centro_ids' => $captacion->esBorrador()
                    ? 'Esta entrevista todavía no está firmada.'
                    : 'Esta captación está descartada.',
            ]);
        }

        return DB::transaction(function () use ($captacion, $centroIds, $superadmin, $empresaExistenteId) {
            $captacion = EmpresaCaptacion::whereKey($captacion->id)->lockForUpdate()->firstOrFail();
            $columnas  = $captacion->only(array_merge(array_values(self::CAMPOS_EMPRESA), array_values(self::CAMPOS_DIAGNOSTICO)));
            $intereses = collect($captacion->familias ?? [])->mapWithKeys(fn ($f) => [$f['nombre'] => (string) ($f['interes'] ?? '')]);
            $centros   = collect($centroIds)->mapWithKeys(fn ($id) => [(int) $id => ['asignada_por_user_id' => $superadmin->id]])->all();

            $empresa = $captacion->empresa;
            if ($empresa) {
                // Ya validada: «Cambiar centros» reemplaza la lista
                $empresa->centrosAsignados()->sync($centros);
            } elseif ($empresaExistenteId) {
                $empresa = Empresa::whereKey($empresaExistenteId)->where('es_simulada', false)->where('es_catalogo', false)->first();
                if (!$empresa) {
                    throw ValidationException::withMessages(['empresa_existente_id' => 'Solo se puede fusionar con una empresa real de Base de datos.']);
                }
                // Completar sin pisar: solo lo que la empresa tiene vacío
                $empresa->update(array_filter($columnas, fn ($v, $col) => filled($v) && blank($empresa->{$col}), ARRAY_FILTER_USE_BOTH));
                $this->familias->anadirPorNombre($empresa->id, $captacion->nombresFamilias());
                $this->familias->guardarIntereses($empresa->id, $intereses
                    ->filter(fn ($interes, $familia) => $interes !== '' && blank($this->familias->interes($empresa->id, $familia)))->all());
                $empresa->centrosAsignados()->syncWithoutDetaching($centros);
            } else {
                $empresa = Empresa::create($columnas + ['es_simulada' => false, 'centro_id' => null]);
                $this->familias->anadirPorNombre($empresa->id, $captacion->nombresFamilias());
                $this->familias->guardarIntereses($empresa->id, $intereses->all());
                $empresa->centrosAsignados()->sync($centros);
            }

            $captacion->forceFill([
                'estado'               => EmpresaCaptacion::ESTADO_VALIDADA,
                'empresa_id'           => $empresa->id,
                'revisada_por_user_id' => $superadmin->id,
                'revisada_at'          => $captacion->revisada_at ?? now(),
            ])->save();

            return $empresa;
        });
    }

    /**
     * Empresas reales de Base de datos que podrían ser esta misma (mismo CIF o nombre parecido),
     * o las que coincidan con la búsqueda del superadmin.
     *
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    public function coincidencias(EmpresaCaptacion $captacion, ?string $busqueda): \Illuminate\Support\Collection
    {
        $termino  = trim((string) ($busqueda ?: $captacion->nombre_comercial));
        $cif      = strtoupper((string) preg_replace('/[\s.-]/', '', (string) $captacion->cif));
        $palabras = collect(preg_split('/\s+/u', mb_strtolower($termino)) ?: [])
            ->filter(fn ($p) => mb_strlen($p) > 3 && !in_array($p, ['empresa', 'grupo', 'servicios'], true))
            ->take(4);
        $like = fn (string $t) => '%' . addcslashes($t, '%_\\') . '%';

        return Empresa::query()
            ->where('es_simulada', false)->where('es_catalogo', false)
            ->where(function ($q) use ($termino, $palabras, $cif, $busqueda, $like) {
                $q->where('nombre_comercial', 'like', $like($termino))->orWhere('razon_social', 'like', $like($termino));
                foreach ($palabras as $p) $q->orWhere('nombre_comercial', 'like', $like($p));
                if ($cif !== '' && !$busqueda) $q->orWhere('cif', $cif);
            })
            ->with('centroEducativo:id,nombre')
            ->orderBy('nombre_comercial')
            ->take(8)
            ->get(['id', 'nombre_comercial', 'razon_social', 'cif', 'municipio', 'centro_id']);
    }

    /** No se usa (spam, duplicada, la empresa no sigue adelante). Queda registrada, no se borra. */
    public function descartar(EmpresaCaptacion $captacion, User $superadmin, ?string $motivo): void
    {
        if ($captacion->estado !== EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION) {
            throw ValidationException::withMessages(['captacion' => 'Solo se pueden descartar captaciones pendientes de validar.']);
        }

        DB::transaction(function () use ($captacion, $superadmin, $motivo) {
            $captacion->forceFill([
                'estado'               => EmpresaCaptacion::ESTADO_DESCARTADA,
                'revisada_por_user_id' => $superadmin->id,
                'revisada_at'          => now(),
                'motivo_descarte'      => $motivo,
            ])->save();
            // Sus retos de demostración ya no se publicarán
            $captacion->borradores()->where('estado', MicroretoBorrador::ESTADO_PENDIENTE)->update([
                'estado'               => MicroretoBorrador::ESTADO_DESCARTADO,
                'revisado_por_user_id' => $superadmin->id,
                'revisado_at'          => now(),
            ]);
        });
    }

    /** @return array{empresas_pendientes: int, retos_pendientes: int} */
    public function resumenPendientes(): array
    {
        return [
            'empresas_pendientes' => EmpresaCaptacion::where('estado', EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION)->count(),
            'retos_pendientes'    => MicroretoBorrador::where('estado', MicroretoBorrador::ESTADO_PENDIENTE)
                ->whereHas('captacion', fn ($q) => $q->whereIn('estado', [EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION, EmpresaCaptacion::ESTADO_VALIDADA]))
                ->count(),
        ];
    }

    /**
     * [{ nombre, interes }] en el orden de $nombres; el interés llega en $intereses o se
     * conserva el que ya tenía esa familia.
     *
     * @param array<int, string> $nombres
     * @param array<string, mixed> $intereses
     * @param array<int, array<string, mixed>> $actuales
     * @return list<array{nombre: string, interes: string|null}>
     */
    private function familiasConInteres(array $nombres, array $intereses, array $actuales): array
    {
        $previos = collect($actuales)->mapWithKeys(fn ($f) => [$f['nombre'] ?? '' => $f['interes'] ?? null]);
        return array_values(array_map(fn ($nombre) => [
            'nombre'  => (string) $nombre,
            'interes' => array_key_exists($nombre, $intereses)
                ? (trim((string) $intereses[$nombre]) ?: null)
                : $previos->get($nombre),
        ], array_unique($nombres)));
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, string> $mapa
     * @return array<string, mixed>
     */
    private function columnas(array $validated, array $mapa): array
    {
        $columnas = [];
        foreach ($mapa as $campo => $columna) {
            if (array_key_exists($campo, $validated)) {
                $columnas[$columna] = $validated[$campo];
            }
        }
        return $columnas;
    }

    /**
     * Lo que se firma: datos de la empresa, familias con su interés y el diagnóstico.
     *
     * @return array<string, mixed>
     */
    private function datosFirmados(EmpresaCaptacion $captacion): array
    {
        $datos = $captacion->only(array_merge(array_values(self::CAMPOS_EMPRESA), array_values(self::CAMPOS_DIAGNOSTICO)));
        $datos['captacion_id'] = $captacion->id;
        $datos['familias'] = $captacion->familias ?? [];
        return $datos;
    }

    /**
     * @param array<string, mixed> $validated
     * @return list<array{clave: string, version: string, titulo: string, texto: string, texto_sha256: string, aceptado: bool}>
     */
    private function consentimientos(array $validated): array
    {
        $lista = [];
        $aceptados = [
            'aviso_legal' => (bool) $validated['aceptaAvisoLegal'],
            'info_rgpd'   => (bool) $validated['aceptaInfoRgpd'],
        ];
        foreach (config('captacion.textos') as $clave => $t) {
            $lista[] = [
                'clave' => $clave, 'version' => $t['version'], 'titulo' => $t['titulo'], 'texto' => $t['texto'],
                'texto_sha256' => hash('sha256', $t['texto']), 'aceptado' => $aceptados[$clave] ?? false,
            ];
        }
        foreach (config('captacion.cesiones') as $clave => $c) {
            $lista[] = [
                'clave' => $clave, 'version' => $c['version'], 'titulo' => 'Cesión de datos a ' . $c['cesionaria'], 'texto' => $c['texto'],
                'texto_sha256' => hash('sha256', $c['texto']), 'aceptado' => (bool) $validated['cesiones'][$clave],
            ];
        }
        return $lista;
    }

    /** PNG del lienzo de firma: se comprueba que de verdad es una imagen PNG razonable. */
    private function decodificarFirma(string $dataUrl): string
    {
        $png = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        $info = $png !== false ? @getimagesizefromstring($png) : false;

        if ($info === false || $info['mime'] !== 'image/png'
            || $info[0] < 50 || $info[1] < 20 || $info[0] > 4000 || $info[1] > 4000) {
            throw ValidationException::withMessages(['firma' => 'La firma no es válida: vuelve a firmar.']);
        }

        return $png;
    }
}

<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\EmpresaConsentimiento;
use App\Models\EmpresaFirma;
use App\Models\User;
use App\Notifications\EmpresaCaptadaPendienteCentro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Empresas captadas por un comercial en la entrevista (app comercial.dualab.es):
 * alta → diagnóstico → firma de consentimientos → colocación en centros por el superadmin.
 *
 * Ciclo de estado_captacion (no fillable, solo lo cambia este servicio):
 *   borrador ──firmar()──▶ pendiente_centro ──asignarCentros()──▶ asignada
 * Mientras no tiene centros, centro_id = NULL y ningún centro la ve (Empresa::scopeDelCentroDe).
 */
class CaptacionEmpresaService
{
    // Campos camelCase de StoreEmpresaComercialRequest → columnas de empresas
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
    // si faltan, se avisa al superadmin para completarlos al colocar la empresa.
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
    public function crear(User $comercial, array $validated): Empresa
    {
        return DB::transaction(function () use ($comercial, $validated) {
            $empresa = Empresa::create($this->columnas($validated, self::CAMPOS_EMPRESA) + [
                'es_simulada' => false,
                'centro_id'   => null,
            ]);
            $empresa->forceFill([
                'creada_por_user_id' => $comercial->id,
                'estado_captacion'   => Empresa::CAPTACION_BORRADOR,
            ])->save();

            $this->familias->anadirPorNombre($empresa->id, $validated['familias']);
            $this->familias->guardarIntereses($empresa->id, $validated['interesesFamilia'] ?? []);

            return $empresa;
        });
    }

    /** @param array<string, mixed> $validated  UpdateEmpresaComercialRequest */
    public function actualizar(Empresa $empresa, array $validated): Empresa
    {
        DB::transaction(function () use ($empresa, $validated) {
            $empresa->update($this->columnas($validated, self::CAMPOS_EMPRESA));
            if (array_key_exists('familias', $validated)) {
                $this->familias->sincronizarPorNombre($empresa->id, $validated['familias']);
            }
            $this->familias->guardarIntereses($empresa->id, $validated['interesesFamilia'] ?? []);
        });

        return $empresa;
    }

    /** @param array<string, mixed> $validated  UpdateDiagnosticoEmpresaRequest */
    public function actualizarDiagnostico(Empresa $empresa, array $validated): Empresa
    {
        $empresa->update($this->columnas($validated, self::CAMPOS_DIAGNOSTICO));
        return $empresa;
    }

    /** @return list<string>  nombres legibles de los datos recomendados que faltan */
    public static function datosIncompletos(Empresa $empresa): array
    {
        $faltan = [];
        foreach (self::DATOS_RECOMENDADOS as $columna => $nombre) {
            if (trim((string) $empresa->{$columna}) === '') $faltan[] = $nombre;
        }
        return $faltan;
    }

    public function tieneDiagnosticoMinimo(Empresa $empresa): bool
    {
        foreach (self::DIAGNOSTICO_MINIMO as $columna) {
            if (trim((string) $empresa->{$columna}) === '') return false;
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
     * Registra la firma y los consentimientos y deja la empresa pendiente de centro.
     *
     * @param array<string, mixed> $validated  FirmarEmpresaComercialRequest
     */
    public function firmar(Empresa $empresa, User $comercial, array $validated, ?string $ip, ?string $userAgent): EmpresaFirma
    {
        // Con los textos de ejemplo no se recogen firmas reales (ver config/captacion.php)
        if (app()->isProduction() && !config('captacion.textos_revisados')) {
            throw ValidationException::withMessages([
                'versiones' => 'Los textos legales aún no están revisados: no se puede firmar todavía.',
            ]);
        }
        if (!$this->tieneDiagnosticoMinimo($empresa)) {
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
            'empresa'      => $this->datosFirmados($empresa),
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
        ];

        // Si algo falla tras escribir el PNG, se borra: nunca queda una firma huérfana en disco
        Storage::disk('local')->put($ruta, $png);
        try {
            $firma = DB::transaction(function () use ($empresa, $comercial, $validated, $uuid, $ruta, $snapshot, $consentimientos, $ahora, $ip, $userAgent) {
                // Doble toque en «Firmar»: la segunda petición espera aquí y ya no la encuentra en borrador
                if (!Empresa::whereKey($empresa->id)->lockForUpdate()->first()?->esBorradorCaptacion()) {
                    throw ValidationException::withMessages(['firma' => 'Esta entrevista ya está firmada.']);
                }

                $firma = new EmpresaFirma();
                $firma->forceFill([
                    'uuid'              => $uuid,
                    'empresa_id'        => $empresa->id,
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

                $empresa->forceFill([
                    'estado_captacion' => Empresa::CAPTACION_PENDIENTE_CENTRO,
                    'firmada_at'       => $ahora,
                ])->save();

                return $firma;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($ruta);
            throw $e;
        }

        // Aviso in-app a DuaLab: hay una empresa nueva que colocar en centros
        Notification::send(
            User::where('role', User::ROLE_SUPERADMIN)->where('is_blocked', false)->get(),
            new EmpresaCaptadaPendienteCentro($empresa, $comercial)
        );

        return $firma;
    }

    /**
     * Coloca la empresa en estos centros (reemplaza la lista). A partir de aquí la ven los
     * docentes de esos centros como una empresa real más (solo lectura del diagnóstico).
     *
     * @param int[] $centroIds
     */
    public function asignarCentros(Empresa $empresa, array $centroIds, User $superadmin): Empresa
    {
        if (!in_array($empresa->estado_captacion, [Empresa::CAPTACION_PENDIENTE_CENTRO, Empresa::CAPTACION_ASIGNADA], true)) {
            throw ValidationException::withMessages([
                'centro_ids' => 'Esta empresa todavía no tiene la entrevista firmada.',
            ]);
        }

        DB::transaction(function () use ($empresa, $centroIds, $superadmin) {
            $empresa->centrosAsignados()->sync(
                collect($centroIds)->mapWithKeys(fn ($id) => [(int) $id => ['asignada_por_user_id' => $superadmin->id]])->all()
            );
            $empresa->forceFill(['estado_captacion' => Empresa::CAPTACION_ASIGNADA])->save();
        });

        return $empresa;
    }

    /** @return array{empresas_pendientes: int, retos_pendientes: int} */
    public function resumenPendientes(): array
    {
        return [
            'empresas_pendientes' => Empresa::where('estado_captacion', Empresa::CAPTACION_PENDIENTE_CENTRO)->count(),
            'retos_pendientes'    => \App\Models\MicroretoBorrador::where('estado', \App\Models\MicroretoBorrador::ESTADO_PENDIENTE)
                ->whereHas('empresa', fn ($q) => $q->whereNull('deleted_at'))->count(),
        ];
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
    private function datosFirmados(Empresa $empresa): array
    {
        $datos = $empresa->only(array_merge(array_values(self::CAMPOS_EMPRESA), array_values(self::CAMPOS_DIAGNOSTICO)));
        $datos['id'] = $empresa->id;
        $datos['familias'] = $empresa->familias()->get()
            ->map(fn ($f) => ['nombre' => $f->nombre, 'interes' => $f->pivot?->getAttribute('interes')])
            ->values()->all();
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

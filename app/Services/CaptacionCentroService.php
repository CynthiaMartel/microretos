<?php

namespace App\Services;

use App\Mail\CentroCaptacionConfirmacionMail;
use App\Models\CaptacionOrigen;
use App\Models\CentroCaptacion;
use App\Models\CentroCaptacionConsentimiento;
use App\Models\CentroEducativo;
use App\Models\User;
use App\Notifications\CentroCaptadoPendienteValidacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Centros educativos captados (centro_captaciones): por la entrevista del comercial o por el
 * formulario de centros de la web. Quedan pendientes de validar; al validarlos DuaLab los da de
 * alta en centro_educativo (o los enlaza con uno que ya existe), que es lo que usa la app.
 *   pendiente_validacion ──validar()──▶ validada
 *                        └─descartar()─▶ descartada
 */
class CaptacionCentroService
{
    // Campos camelCase de StoreCentroComercialRequest → columnas de centro_captaciones
    private const CAMPOS = [
        'accion'            => 'accion',
        'centroNombre'      => 'centro_nombre',
        'direccion'         => 'direccion',
        'provincia'         => 'provincia',
        'municipio'         => 'municipio',
        'familias'          => 'familias',
        'contactoNombre'    => 'contacto_nombre',
        'contactoApellidos' => 'contacto_apellidos',
        'contactoCargo'     => 'contacto_cargo',
        'contactoEmail'     => 'contacto_email',
        'contactoTelefono'  => 'contacto_telefono',
        'comentarios'       => 'comentarios',
    ];

    private const MAX_COINCIDENCIAS = 8;

    /**
     * Textos que el centro acepta en la tablet: la información de protección de datos (obligatoria)
     * y las cesiones (opcionales), con la versión que la tablet devuelve.
     *
     * @return array{revisados: bool, textos: list<array<string, mixed>>, cesiones: list<array<string, mixed>>}
     */
    public function textosVigentes(): array
    {
        $textos = [];
        foreach (config('captacion.centros.textos') as $clave => $t) {
            $textos[] = ['clave' => $clave, 'version' => $t['version'], 'titulo' => $t['titulo'], 'texto' => $t['texto']];
        }
        $cesiones = [];
        foreach (config('captacion.cesiones') as $clave => $c) {
            $cesiones[] = ['clave' => $clave, 'version' => $c['version'], 'cesionaria' => $c['cesionaria'], 'texto' => $c['texto']];
        }
        return ['revisados' => (bool) config('captacion.textos_revisados'), 'textos' => $textos, 'cesiones' => $cesiones];
    }

    /**
     * Entrevista a un centro en la app comercial: queda pendiente de validar, con lo que aceptó.
     *
     * @param array<string, mixed> $validated  StoreCentroComercialRequest
     */
    public function crearDesdeEntrevista(User $comercial, array $validated): CentroCaptacion
    {
        // Con los textos de ejemplo no se recogen aceptaciones reales (ver config/captacion.php)
        if (app()->isProduction() && !config('captacion.textos_revisados')) {
            throw ValidationException::withMessages([
                'versiones' => 'Los textos legales aún no están revisados: no se pueden registrar centros todavía.',
            ]);
        }
        // Alta sin conexión que se reenvía al sincronizar: no se duplica
        if (!empty($validated['clienteUuid'])) {
            $existente = CentroCaptacion::where('cliente_uuid', $validated['clienteUuid'])
                ->where('creada_por_user_id', $comercial->id)->first();
            if ($existente) return $existente;
        }

        $captacion = DB::transaction(function () use ($comercial, $validated) {
            $captacion = new CentroCaptacion($this->columnas($validated));
            $captacion->forceFill([
                'origen_id'          => CaptacionOrigen::ENTREVISTA,
                'cliente_uuid'       => $validated['clienteUuid'] ?? null,
                'creada_por_user_id' => $comercial->id,
                'estado'             => CentroCaptacion::ESTADO_PENDIENTE_VALIDACION,
            ])->save();

            foreach ($this->consentimientos($validated) as $c) {
                (new CentroCaptacionConsentimiento())->forceFill(['centro_captacion_id' => $captacion->id] + $c)->save();
            }
            return $captacion;
        });

        $this->avisar($captacion, $comercial);
        return $captacion;
    }

    /**
     * Formulario de centros de la web (webhook del frontoffice). Sin consentimientos: el
     * formulario web no tiene casillas, solo el texto informativo.
     *
     * @param array<string, mixed> $datos  columnas de centro_captaciones
     */
    public function crearDesdeFormulario(string $origenRef, array $datos): CentroCaptacion
    {
        $existente = CentroCaptacion::where('origen_id', CaptacionOrigen::FORMULARIO)->where('origen_ref', $origenRef)->first();
        if ($existente) return $existente; // reintento del webhook

        $captacion = new CentroCaptacion($datos);
        $captacion->forceFill([
            'origen_id'  => CaptacionOrigen::FORMULARIO,
            'origen_ref' => $origenRef,
            'estado'     => CentroCaptacion::ESTADO_PENDIENTE_VALIDACION,
        ])->save();

        // El frontoffice ya le envió su confirmación por email: aquí solo el aviso interno
        $this->avisar($captacion, null, enviarConfirmacion: false);
        return $captacion;
    }

    /**
     * Valida el centro: lo enlaza con un centro_educativo que ya existe o da de alta uno nuevo
     * con su nombre y municipio. Desde ese momento se puede usar en DuaLab (usuarios, empresas…).
     */
    public function validar(CentroCaptacion $captacion, User $superadmin, ?int $centroExistenteId): CentroEducativo
    {
        if ($captacion->estado !== CentroCaptacion::ESTADO_PENDIENTE_VALIDACION) {
            throw ValidationException::withMessages(['captacion' => 'Este centro ya se ha revisado.']);
        }

        return DB::transaction(function () use ($captacion, $superadmin, $centroExistenteId) {
            if ($centroExistenteId) {
                $centro = CentroEducativo::findOrFail($centroExistenteId);
                if (!$centro->municipio && $captacion->municipio) {
                    $centro->forceFill(['municipio' => $captacion->municipio])->save();
                }
            } else {
                // nombre es único en centro_educativo (también los borrados): si ya existe, enlazarlo
                if (CentroEducativo::withTrashed()->where('nombre', $captacion->centro_nombre)->exists()) {
                    throw ValidationException::withMessages([
                        'centro_existente_id' => "Ya hay un centro llamado «{$captacion->centro_nombre}»: elígelo en «Es un centro que ya está en DuaLab».",
                    ]);
                }
                $centro = CentroEducativo::create(['nombre' => $captacion->centro_nombre, 'municipio' => $captacion->municipio]);
            }

            $captacion->forceFill([
                'estado'               => CentroCaptacion::ESTADO_VALIDADA,
                'centro_educativo_id'  => $centro->id,
                'revisada_por_user_id' => $superadmin->id,
                'revisada_at'          => now(),
            ])->save();

            return $centro;
        });
    }

    public function descartar(CentroCaptacion $captacion, User $superadmin, ?string $motivo): void
    {
        if ($captacion->estado !== CentroCaptacion::ESTADO_PENDIENTE_VALIDACION) {
            throw ValidationException::withMessages(['captacion' => 'Solo se pueden descartar centros pendientes de validar.']);
        }
        $captacion->forceFill([
            'estado'               => CentroCaptacion::ESTADO_DESCARTADA,
            'revisada_por_user_id' => $superadmin->id,
            'revisada_at'          => now(),
            'motivo_descarte'      => $motivo,
        ])->save();
    }

    /**
     * Centros de DuaLab que podrían ser este mismo (para no darlo de alta dos veces), o los que
     * coincidan con la búsqueda del superadmin.
     *
     * @return Collection<int, CentroEducativo>
     */
    public function coincidencias(CentroCaptacion $captacion, ?string $busqueda): Collection
    {
        $termino = trim((string) ($busqueda ?: $captacion->centro_nombre));
        // Palabras significativas del nombre ("IES", "CIFP"… no distinguen)
        $palabras = collect(preg_split('/\s+/u', mb_strtolower($termino)) ?: [])
            ->filter(fn ($p) => mb_strlen($p) > 3 && !in_array($p, ['cifp', 'colegio', 'instituto', 'centro'], true))
            ->take(4);

        return CentroEducativo::query()
            ->where(function ($q) use ($termino, $palabras) {
                $q->where('nombre', 'like', '%' . addcslashes($termino, '%_\\') . '%');
                foreach ($palabras as $p) $q->orWhere('nombre', 'like', '%' . addcslashes($p, '%_\\') . '%');
            })
            ->orderBy('nombre')
            ->take(self::MAX_COINCIDENCIAS)
            ->get(['id', 'nombre', 'municipio']);
    }

    /** Centros pendientes de validar (aviso al iniciar sesión) */
    public function pendientes(): int
    {
        return CentroCaptacion::where('estado', CentroCaptacion::ESTADO_PENDIENTE_VALIDACION)->count();
    }

    private function avisar(CentroCaptacion $captacion, ?User $comercial, bool $enviarConfirmacion = true): void
    {
        Notification::send(
            User::where('role', User::ROLE_SUPERADMIN)->where('is_blocked', false)->get(),
            new CentroCaptadoPendienteValidacion($captacion, $comercial)
        );

        // Mismo correo que recibe quien rellena el formulario de centros de la web (en cola)
        if ($enviarConfirmacion) {
            try {
                Mail::to($captacion->contacto_email)->queue(new CentroCaptacionConfirmacionMail($captacion));
            } catch (\Throwable $e) {
                // Sin correo no se pierde la captación: ya está guardada y avisada
                Log::warning('No se pudo encolar la confirmación al centro captado.', ['centro_captacion_id' => $captacion->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function columnas(array $validated): array
    {
        $columnas = [];
        foreach (self::CAMPOS as $campo => $columna) {
            if (array_key_exists($campo, $validated)) $columnas[$columna] = $validated[$campo];
        }
        $columnas['familias'] = array_values(array_unique($columnas['familias'] ?? []));
        return $columnas;
    }

    /**
     * @param array<string, mixed> $validated
     * @return list<array{clave: string, version: string, texto_sha256: string, aceptado: bool}>
     */
    private function consentimientos(array $validated): array
    {
        $lista = [];
        foreach (config('captacion.centros.textos') as $clave => $t) {
            $lista[] = ['clave' => $clave, 'version' => $t['version'], 'texto_sha256' => hash('sha256', $t['texto']), 'aceptado' => (bool) $validated['aceptaInfoRgpd']];
        }
        foreach (config('captacion.cesiones') as $clave => $c) {
            $lista[] = ['clave' => $clave, 'version' => $c['version'], 'texto_sha256' => hash('sha256', $c['texto']), 'aceptado' => (bool) $validated['cesiones'][$clave]];
        }
        return $lista;
    }
}

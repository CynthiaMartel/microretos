<?php

namespace App\Services;

use App\Mail\FormularioEmpresaMail;
use App\Mail\FormularioEmpresaRespondidoMail;
use App\Models\CaptacionOrigen;
use App\Models\EmpresaCaptacion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Entrevista por correo: cuando no da tiempo a hacerla en persona, el comercial envía a la
 * empresa un enlace (comercial.dualab.es/formulario/{token}) y ella misma rellena, sin cuenta,
 * los pasos de la entrevista: sus datos, las siete preguntas y la firma. Basta con su email: si
 * la entrevista aún no existe, se crea con él (crearYEnviar).
 *
 * Del token solo se guarda su huella SHA-256: con la BD no se puede abrir el formulario. Cada
 * envío genera un token nuevo y anula el anterior. El enlace deja de valer al caducar o al
 * firmarse la entrevista (EmpresaCaptacion::formularioAbierto).
 */
class FormularioEmpresaService
{
    public function __construct(private CaptacionEmpresaService $captacion) {}

    /**
     * Entrevista nueva de la que solo se sabe el email: se crea y se le envía el enlace.
     *
     * @return array{0: EmpresaCaptacion, 1: string}  la captación y el enlace
     */
    public function crearYEnviar(User $comercial, string $email): array
    {
        $captacion = new EmpresaCaptacion(['email_general' => $email]);
        $captacion->forceFill([
            'origen_id'          => CaptacionOrigen::ENTREVISTA,
            'creada_por_user_id' => $comercial->id,
            'estado'             => EmpresaCaptacion::ESTADO_BORRADOR,
        ])->save();

        return [$captacion, $this->enviar($captacion, $comercial, $email)];
    }

    /** Crea un enlace nuevo, lo envía por correo a la empresa y lo devuelve (para copiarlo) */
    public function enviar(EmpresaCaptacion $captacion, User $comercial, string $email): string
    {
        if (!$captacion->esBorrador()) {
            throw ValidationException::withMessages(['email' => 'La entrevista ya está firmada.']);
        }

        $token = Str::random(64);
        $captacion->forceFill([
            'formulario_token_hash'    => hash('sha256', $token),
            'formulario_expira_at'     => now()->addDays((int) config('captacion.formulario.dias_validez')),
            'formulario_enviado_at'    => now(),
            'formulario_respondido_at' => null,
        ])->save();
        // Sin email todavía: el de la invitación (la empresa puede cambiarlo en sus datos)
        if (blank($captacion->email_general)) $captacion->update(['email_general' => $email]);

        $enlace = rtrim((string) config('captacion.url_app_comercial'), '/') . '/formulario/' . $token;
        Mail::to($email)->queue(new FormularioEmpresaMail($captacion, $comercial, $enlace));

        return $enlace;
    }

    /** El comercial anula el enlace (enviado a quien no era, la empresa ya no lo necesita…) */
    public function anular(EmpresaCaptacion $captacion): void
    {
        $captacion->forceFill(['formulario_token_hash' => null, 'formulario_expira_at' => null])->save();
    }

    /** Captación del enlace, o null si no existe; si existe pero ya no vale, formularioAbierto() lo dice */
    public function buscar(string $token): ?EmpresaCaptacion
    {
        if (strlen($token) !== 64) return null;

        return EmpresaCaptacion::where('formulario_token_hash', hash('sha256', $token))->first();
    }

    /**
     * Lo que ve quien abre el enlace: los datos de su propia empresa y sus respuestas (para
     * seguir donde lo dejó), los textos legales de la firma y la información de protección de
     * datos. Nunca las notas internas, el estado ni quién la captó.
     *
     * @return array<string, mixed>
     */
    public function datosPublicos(EmpresaCaptacion $captacion): array
    {
        $privacidad = config('captacion.formulario.privacidad');

        return [
            'empresa' => [
                ...$captacion->only([
                    'nombre_comercial', 'razon_social', 'cif', 'sector', 'tamano', 'nivel_digitalizacion',
                    'web', 'actividad', 'persona_contacto', 'telefono', 'email_general', 'direccion',
                    'municipio', 'provincia', 'codigo_postal',
                    'dia_a_normal', 'friccion_area', 'friccion_problema', 'consecuencias',
                    'restricciones', 'lo_que_no_quieren', 'expectativas_alumno',
                ]),
                'familias' => array_values(array_map(fn ($f) => [
                    'nombre' => $f['nombre'] ?? '', 'interes' => $f['interes'] ?? null,
                ], $captacion->familias ?? [])),
            ],
            'expira_at'      => $captacion->formulario_expira_at?->toIso8601String(),
            'privacidad'     => ['version' => $privacidad['version'], 'titulo' => $privacidad['titulo'], 'texto' => $privacidad['texto']],
            'textos_legales' => $this->captacion->textosVigentes(),
        ];
    }

    /** @param array<string, mixed> $validated  GuardarEmpresaFormularioRequest */
    public function guardarEmpresa(EmpresaCaptacion $captacion, array $validated): void
    {
        $this->captacion->actualizar($captacion, $validated);
        $this->anotarPrivacidad($captacion, $validated);
    }

    /** @param array<string, mixed> $validated  GuardarFormularioEmpresaRequest */
    public function guardarRespuestas(EmpresaCaptacion $captacion, array $validated): void
    {
        $this->captacion->actualizarDiagnostico($captacion, $validated);
        $this->anotarPrivacidad($captacion, $validated);
    }

    /**
     * La empresa firma desde el enlace: misma firma y consentimientos que en la tablet, con
     * canal 'formulario_correo'. La entrevista queda pendiente de validar y se avisa al comercial.
     *
     * @param array<string, mixed> $validated  FirmarFormularioEmpresaRequest
     */
    public function firmar(EmpresaCaptacion $captacion, array $validated, ?string $ip, ?string $userAgent): void
    {
        $comercial = $captacion->creadaPor;
        if (!$comercial) {
            throw ValidationException::withMessages(['formulario' => 'Este formulario ya no admite respuestas.']);
        }

        // firmar() bloquea la fila y rechaza la segunda firma si se pulsa dos veces
        $this->captacion->firmar($captacion, $comercial, $validated, $ip, $userAgent, 'formulario_correo');
        $captacion->forceFill(['formulario_respondido_at' => now()])->save();

        if ($comercial->email && !$comercial->is_blocked) {
            Mail::to($comercial->email)->queue(new FormularioEmpresaRespondidoMail($captacion->refresh(), $comercial->name));
        }
    }

    /** Versión de la información de protección de datos que aceptó al empezar a rellenarlo */
    private function anotarPrivacidad(EmpresaCaptacion $captacion, array $validated): void
    {
        if ($captacion->formulario_privacidad_version !== $validated['privacidadVersion']) {
            $captacion->forceFill(['formulario_privacidad_version' => $validated['privacidadVersion']])->save();
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactarEmpresaRequest;
use App\Http\Requests\EnviarValidacionEmpresaRequest;
use App\Http\Requests\VerificarAccesoEmpresasRequest;
use App\Models\Empresa;
use App\Models\Microproyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmpresaContactoController extends Controller
{
    private const SESION_DESBLOQUEO = 'empresas_contacto_desbloqueado';

    /**
     * Verifica la contraseña especial del módulo Empresas.
     * La contraseña se configura en .env como EMPRESAS_ACCESS_PASSWORD.
     * Al acertarla se marca la sesión: contactar() y enviarValidacion() la exigen.
     */
    public function verificarAcceso(VerificarAccesoEmpresasRequest $request): JsonResponse
    {
        $pass = (string) config('services.empresas.access_password');

        if ($pass === '' || !hash_equals($pass, $request->validated('password'))) {
            return response()->json(['success' => false, 'message' => 'Contraseña incorrecta.'], 401);
        }

        $request->session()->put(self::SESION_DESBLOQUEO, true);

        return response()->json(['success' => true]);
    }

    /**
     * Envía un correo libre al email de la empresa.
     */
    public function contactar(ContactarEmpresaRequest $request, int $id): JsonResponse
    {
        $this->exigirDesbloqueo($request);
        $empresa = Empresa::findOrFail($id);
        $this->autorizarEmpresa($request, $empresa);

        $data = $request->validated();

        $destinatario = $empresa->email_contacto ?: $empresa->email_general;

        if (!$destinatario) {
            return response()->json([
                'success' => false,
                'message' => 'La empresa no tiene ningún email registrado.',
            ], 422);
        }

        Mail::raw($data['mensaje'], function ($mail) use ($empresa, $data, $destinatario) {
            $mail->to($destinatario, $empresa->nombre_comercial)
                 ->replyTo($data['remitente'])
                 ->subject($data['asunto']);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Envía por email el enlace de validación de un microproyecto publicado a una empresa.
     */
    public function enviarValidacion(EnviarValidacionEmpresaRequest $request, int $id): JsonResponse
    {
        $this->exigirDesbloqueo($request);
        $empresa = Empresa::findOrFail($id);
        $this->autorizarEmpresa($request, $empresa);

        $data = $request->validated();

        $proyecto = Microproyecto::where('uuid', $data['proyecto_uuid'])
            ->whereIn('estado', ['propuesta', 'validado'])
            ->firstOrFail();

        $destinatario = $empresa->email_contacto ?: $empresa->email_general;

        if (!$destinatario) {
            return response()->json([
                'success' => false,
                'message' => 'La empresa no tiene ningún email registrado.',
            ], 422);
        }

        $base = rtrim(config('app.frontend_url'), '/');
        $url  = "{$base}/startup/landing/{$proyecto->token_empresa}";

        $nombre = $empresa->persona_contacto
            ? "Estimado/a {$empresa->persona_contacto},"
            : "Estimado equipo de {$empresa->nombre_comercial},";

        $cuerpo  = "{$nombre}\n\n";
        $cuerpo .= "Nos ponemos en contacto con vosotros desde DUALAB para compartir el microproyecto ";
        $cuerpo .= "que nuestro alumnado ha desarrollado en colaboración con vuestra empresa.\n\n";
        $cuerpo .= "📋 Proyecto: {$proyecto->titulo}\n\n";
        $cuerpo .= "Os invitamos a revisar el trabajo del equipo y, si lo consideráis oportuno, ";
        $cuerpo .= "compartir vuestra valoración a través del siguiente enlace:\n\n";
        $cuerpo .= "🔗 {$url}\n\n";

        if (!empty($data['mensaje'])) {
            $cuerpo .= $data['mensaje'] . "\n\n";
        }

        $cuerpo .= "El proceso es muy sencillo: accedéis al enlace, revisáis la propuesta del equipo ";
        $cuerpo .= "y respondéis unas breves preguntas de valoración.\n\n";
        $cuerpo .= "Muchas gracias por vuestra colaboración.\n\n";
        $cuerpo .= "Un saludo,\nEquipo DUALAB · ViaÓptima";

        Mail::raw($cuerpo, function ($mail) use ($empresa, $data, $destinatario, $proyecto) {
            $mail->to($destinatario, $empresa->nombre_comercial)
                 ->replyTo($data['remitente'])
                 ->subject("Validación de microproyecto: {$proyecto->titulo}");
        });

        // Marcar el proyecto como "enviado a empresa por mail" para mostrar la etiqueta en la miniatura
        $proyecto->update(['enviado_a_empresa_mail' => true]);

        return response()->json(['success' => true]);
    }

    // Los envíos a empresas exigen haber introducido la contraseña del módulo en esta
    // sesión (verificarAcceso). 423 para que el frontend reabra el modal de contraseña.
    private function exigirDesbloqueo(Request $request): void
    {
        if (!$request->session()->get(self::SESION_DESBLOQUEO)) {
            abort(423, 'Introduce la contraseña del módulo Empresas para enviar correos.');
        }
    }

    // Docente y admin solo pueden contactar/validar con empresas de su propio centro;
    // superadmin no tiene esta restricción.
    private function autorizarEmpresa(Request $request, Empresa $empresa): void
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if (($user->isDocente() || $user->isAdmin()) && $empresa->perteneceAlCentroDe($user)) {
            return;
        }

        abort(403, 'No autorizado: la empresa no pertenece a tu centro educativo.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Microproyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmpresaContactoController extends Controller
{
    /**
     * Verifica la contraseña especial del módulo Empresas.
     * La contraseña se configura en .env como EMPRESAS_ACCESS_PASSWORD.
     */
    public function verificarAcceso(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $pass = config('services.empresas.access_password');

        if ($request->password !== $pass) {
            return response()->json(['success' => false, 'message' => 'Contraseña incorrecta.'], 401);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Envía un correo libre al email de la empresa.
     */
    public function contactar(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);

        $data = $request->validate([
            'remitente' => 'required|email|max:255',
            'asunto'    => 'required|string|max:200',
            'mensaje'   => 'required|string|max:5000',
        ]);

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
    public function enviarValidacion(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);

        $data = $request->validate([
            'remitente'     => 'required|email|max:255',
            'proyecto_uuid' => 'required|string|exists:microproyectos,uuid',
            'mensaje'       => 'nullable|string|max:2000',
        ]);

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

        $isLocal = app()->environment('local');
        $base = $isLocal ? 'http://localhost:5173' : 'https://dualab.es';
        $url  = "{$base}/startup/landing/{$proyecto->token_empresa}";

        $nombre = $empresa->persona_contacto
            ? "Estimado/a {$empresa->persona_contacto},"
            : "Estimado equipo de {$empresa->nombre_comercial},";

        $cuerpo  = "{$nombre}\n\n";
        $cuerpo .= "Nos ponemos en contacto con vosotros desde DuaLab para compartir el microproyecto ";
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
        $cuerpo .= "Un saludo,\nEquipo DuaLab · ViaÓptima";

        Mail::raw($cuerpo, function ($mail) use ($empresa, $data, $destinatario, $proyecto) {
            $mail->to($destinatario, $empresa->nombre_comercial)
                 ->replyTo($data['remitente'])
                 ->subject("Validación de microproyecto: {$proyecto->titulo}");
        });

        // Marcar el proyecto como "enviado a empresa por mail" para mostrar la etiqueta en la miniatura
        $proyecto->update(['enviado_a_empresa_mail' => true]);

        return response()->json(['success' => true]);
    }
}

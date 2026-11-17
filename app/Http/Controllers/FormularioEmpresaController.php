<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirmarFormularioEmpresaRequest;
use App\Http\Requests\GuardarEmpresaFormularioRequest;
use App\Http\Requests\GuardarFormularioEmpresaRequest;
use App\Models\EmpresaCaptacion;
use App\Services\FormularioEmpresaService;
use Illuminate\Http\JsonResponse;

/**
 * Entrevista por correo (comercial.dualab.es/formulario/{token}), rutas
 * /public/formulario-empresa/{token}: la empresa rellena sin cuenta los pasos de la entrevista
 * (sus datos, las preguntas y la firma). El token es la única credencial (FormularioEmpresaService).
 */
class FormularioEmpresaController extends Controller
{
    public function __construct(private FormularioEmpresaService $formulario) {}

    /** GET /public/formulario-empresa/{token} */
    public function show(string $token): JsonResponse
    {
        $captacion = $this->formulario->buscar($token);
        if ($error = $this->cerrado($captacion)) return $error;

        return response()->json(['data' => $this->formulario->datosPublicos($captacion)]);
    }

    /** PUT /public/formulario-empresa/{token}/empresa — paso «Empresa» */
    public function guardarEmpresa(GuardarEmpresaFormularioRequest $request, string $token): JsonResponse
    {
        $captacion = $this->formulario->buscar($token);
        if ($error = $this->cerrado($captacion)) return $error;

        $this->formulario->guardarEmpresa($captacion, $request->validated());

        return response()->json(['data' => $this->formulario->datosPublicos($captacion->refresh())]);
    }

    /** PUT /public/formulario-empresa/{token} — respuestas a las preguntas (guardar y seguir más tarde) */
    public function guardar(GuardarFormularioEmpresaRequest $request, string $token): JsonResponse
    {
        $captacion = $this->formulario->buscar($token);
        if ($error = $this->cerrado($captacion)) return $error;

        $this->formulario->guardarRespuestas($captacion, $request->validated());

        return response()->json(['data' => ['guardado' => true]]);
    }

    /** POST /public/formulario-empresa/{token}/firma — paso «Firma»: cierra la entrevista */
    public function firmar(FirmarFormularioEmpresaRequest $request, string $token): JsonResponse
    {
        $captacion = $this->formulario->buscar($token);
        if ($error = $this->cerrado($captacion)) return $error;

        $this->formulario->firmar($captacion, $request->validated(), $request->ip(), $request->userAgent());

        return response()->json(['data' => ['firmado' => true]]);
    }

    /** Respuesta si el enlace no existe o ya no vale, o null si se puede usar */
    private function cerrado(?EmpresaCaptacion $captacion): ?JsonResponse
    {
        if (!$captacion) {
            return response()->json(['error' => 'Este enlace no es válido. Pide uno nuevo a la persona de DuaLab que te lo envió.', 'motivo' => 'no_existe'], 404);
        }
        if ($captacion->formularioAbierto()) return null;

        [$mensaje, $motivo] = match (true) {
            $captacion->formulario_respondido_at !== null => ['Ya has completado y firmado la entrevista. ¡Gracias!', 'respondido'],
            !$captacion->esBorrador()                     => ['La entrevista de tu empresa ya está completada. ¡Gracias!', 'completada'],
            default                                       => ['Este enlace ha caducado. Pide uno nuevo a la persona de DuaLab que te lo envió.', 'caducado'],
        };

        return response()->json(['error' => $mensaje, 'motivo' => $motivo], 410);
    }
}

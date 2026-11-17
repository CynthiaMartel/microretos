<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AceptaPrivacidadFormulario;

/**
 * PUT /public/formulario-empresa/{token} — la empresa guarda sus respuestas a las preguntas de
 * la entrevista (al pasar de pregunta, para poder seguir más tarde): mismos campos y límites
 * del diagnóstico, ninguno obligatorio todavía (lo son al firmar, CaptacionEmpresaService).
 * El token lo comprueba el controller; aquí no hay usuario.
 */
class GuardarFormularioEmpresaRequest extends UpdateDiagnosticoEmpresaRequest
{
    use AceptaPrivacidadFormulario;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_map(fn ($regla) => str_replace('required|', 'nullable|', $regla), parent::rules())
            + $this->reglasPrivacidad();
    }
}

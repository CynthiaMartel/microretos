<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarMicroretoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // El frontend manda empresaId (camelCase), normalizamos antes de validar
        $this->merge([
            'empresa_id' => $this->empresa_id ?? $this->empresaId,
        ]);
    }

    public function authorize(): bool
    {
        // La autorización de rol (docente/admin/superadmin) ya la resuelve
        // el middleware 'docente' en routes/api.php; la pertenencia al centro
        // se comprueba aparte en el controller (perteneceAlCentroDe).
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'        => 'required|integer|exists:empresas,id',
            'empresaNombre'     => 'required|string',
            'empresaSector'     => 'required|string',
            'friccionProblema'  => 'required|string',
            'ciclo_nombre'      => 'required|string',
            'ciclo_id'          => 'required',
            'nivelGrupo'        => 'required|string',
            'cursoSeleccionado' => 'required|in:1,2,ambos_cursos',
            'modulo_id'         => 'nullable|array',
            'cantidad'          => 'required|integer|min:1|max:5',
            'familia'           => 'nullable|string',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluarEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'evaluacion'                     => 'required|array',
            'evaluacion.ras'                 => 'required|array',
            'evaluacion.ras.*.ra'            => 'required|string',
            'evaluacion.ras.*.nivel'         => 'required|in:no_alcanzado,en_proceso,alcanzado,superado',
            'evaluacion.ras.*.observaciones' => 'nullable|string|max:1000',
            'evaluacion.nota_opcional'       => 'nullable|numeric|min:0|max:10',
            'nota_docente'                   => 'nullable|numeric|min:0|max:10',
            'observaciones_docente'          => 'nullable|string|max:2000',
        ];
    }
}

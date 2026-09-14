<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarFaseEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nota_docente'          => 'nullable|numeric|min:0|max:10',
            'observaciones_docente' => 'nullable|string|max:2000',
        ];
    }
}

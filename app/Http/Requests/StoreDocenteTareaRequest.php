<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocenteTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:200'],
            // Al importar las tareas antiguas de localStorage llegan ya marcadas
            'hecha' => ['sometimes', 'boolean'],
        ];
    }
}

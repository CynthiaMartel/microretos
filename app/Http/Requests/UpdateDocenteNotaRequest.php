<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocenteNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'texto' => ['sometimes', 'required', 'string', 'max:1000'],
            'fecha' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarAliasMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('alias')) {
            $this->merge(['alias' => trim(strip_tags((string) $this->input('alias')))]);
        }
    }

    public function rules(): array
    {
        return [
            'alias' => 'required|string|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'alias.required' => 'El alias no puede estar vacío.',
            'alias.max'      => 'El alias es demasiado largo.',
        ];
    }
}

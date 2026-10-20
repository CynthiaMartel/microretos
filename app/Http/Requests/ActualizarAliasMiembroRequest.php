<?php

namespace App\Http\Requests;

use App\Rules\AliasAlumnado;
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
            // Solo alias generados: un texto libre permitiría escribir el nombre real
            'alias' => ['required', 'string', new AliasAlumnado()],
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

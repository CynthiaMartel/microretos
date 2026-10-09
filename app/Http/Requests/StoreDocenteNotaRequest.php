<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocenteNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:1000'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}

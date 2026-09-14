<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SugerirHallazgoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'existentes'    => 'sometimes|array|max:20',
            'existentes.*'  => 'string|max:500',
            'sugeridas_ia'  => 'sometimes|array|max:20',
            'sugeridas_ia.*' => 'string|max:500',
        ];
    }
}

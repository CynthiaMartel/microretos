<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// POST /captacion/borradores/{uuid}/publicar — centros en los que se publica el reto. Tienen
// que ser centros de la empresa (se comprueba en BorradorRetoService). Rol: 'superadmin'.
class PublicarBorradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'centro_ids'   => 'required|array|min:1|max:50',
            'centro_ids.*' => 'integer|distinct',
        ];
    }
}

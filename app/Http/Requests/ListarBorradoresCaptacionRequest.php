<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /captacion/borradores — en_curso=1: retos de empresas que la comercial aún está entrevistando */
class ListarBorradoresCaptacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Red DuaLab: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['en_curso' => 'sometimes|boolean'];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** POST /ciclos — alta de ciclo formativo desde «Base de datos» (gestión de familias y ciclos). */
class StoreCicloRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está restringida por el middleware 'superadmin'.
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255',
            'familia_id'  => 'required|integer|exists:familias,id,deleted_at,NULL',
            'grado'       => 'nullable|string|max:100',
            'siglasGrado' => 'nullable|string|max:3',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Rules\MiembroDelEquipo;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipoTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion' => 'sometimes|string|max:500',
            'responsable' => ['nullable', 'string', 'max:100', new MiembroDelEquipo($this->route('token'))],
            'estado'      => 'sometimes|in:pendiente,en_progreso,realizado',
        ];
    }
}

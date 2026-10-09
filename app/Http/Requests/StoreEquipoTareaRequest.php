<?php

namespace App\Http\Requests;

use App\Rules\MiembroDelEquipo;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion' => 'required|string|max:500',
            'tipo'        => 'sometimes|in:proceso,detalle_solucion',
            'responsable' => ['nullable', 'string', 'max:100', new MiembroDelEquipo($this->route('token'))],
            'estado'      => 'nullable|in:pendiente,en_progreso,realizado',
        ];
    }
}

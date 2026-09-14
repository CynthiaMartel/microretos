<?php

namespace App\Http\Requests;

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
            'responsable' => 'nullable|string|max:100',
            'estado'      => 'nullable|in:pendiente,en_progreso,realizado',
        ];
    }
}

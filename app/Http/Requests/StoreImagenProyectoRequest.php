<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImagenProyectoRequest extends FormRequest
{
    // Autorización real (pertenencia del proyecto/equipo) se resuelve en el controller,
    // igual que el resto de subidas de este proyecto (StoreEquipoPrototipoRequest, etc.).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:8000',
                'mimes:png,jpg,jpeg,gif,webp',
            ],
            'label' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Solo se admiten imágenes (PNG, JPG, GIF o WEBP).',
            'file.max'   => 'La imagen supera el límite de 8 MB.',
        ];
    }
}

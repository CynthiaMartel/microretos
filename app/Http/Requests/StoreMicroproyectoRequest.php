<?php

namespace App\Http\Requests;

use App\Models\Microreto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear un reto ajustado (POST /startup/proyectos): el asistente lo crea con el título y el
 * reto, y el resto llega después por el PUT (UpdateMicroproyectoRequest).
 * El reto tiene que ser uno que el usuario puede ver: los de su centro (superadmin, todos).
 */
class StoreMicroproyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['titulo', 'curso'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => strip_tags($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo'       => 'required|string|max:255',
            'microreto_id' => ['required', 'integer', 'exists:microretos,id', function ($attribute, $value, $fail) {
                $user = $this->user();
                if ($user->isSuperAdmin()) {
                    return;
                }
                if (!$user->centro_educativo_id || !Microreto::whereKey($value)->delCentroDe($user)->exists()) {
                    $fail('El reto no es de tu centro.');
                }
            }],
            'empresa_id'   => 'nullable|exists:empresas,id',
            'centro_id'    => 'nullable|exists:centro_educativo,id',
            'familia_id'   => 'nullable|exists:familias,id',
            'ciclo_id'     => 'nullable|exists:ciclos_formativos,id',
            'curso'        => 'nullable|string|max:100',
        ];
    }
}

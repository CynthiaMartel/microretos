<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Sin sesión Sanctum — el acceso lo protege el token del equipo en la URL (ver
// EquipoPublicoController::actualizarAliasMiembro), igual que el resto de endpoints
// públicos del workspace de alumnado.
class ActualizarAliasMiembroPublicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('alias')) {
            $this->merge(['alias' => trim(strip_tags((string) $this->input('alias')))]);
        }
    }

    public function rules(): array
    {
        return [
            'alias' => 'required|string|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'alias.required' => 'El alias no puede estar vacío.',
            'alias.max'      => 'El alias es demasiado largo.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Encuentro;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEncuentroColaboradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', function ($attribute, $value, $fail) {
                $encuentro = Encuentro::find($this->route('id'));
                if (!$encuentro) return;

                if ((int) $value === (int) $encuentro->user_id) {
                    $fail('El propietario del encuentro no puede añadirse como colaborador.');
                    return;
                }

                $candidato = User::find($value);
                if (!$candidato || !$candidato->isDocente()) {
                    $fail('Solo se pueden añadir docentes como colaboradores.');
                    return;
                }

                $centroPropietario = $encuentro->docente?->centro_educativo_id;
                if ($centroPropietario === null || $candidato->centro_educativo_id !== $centroPropietario) {
                    $fail('Solo puedes compartir el encuentro con docentes de tu mismo centro educativo.');
                }
            }],
            'puede_editar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Selecciona un docente para compartir el encuentro.',
            'user_id.exists'   => 'El docente seleccionado no existe.',
        ];
    }
}

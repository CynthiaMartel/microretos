<?php

namespace App\Http\Requests;

use App\Models\Encuentro;
use App\Models\User;
use App\Services\ModulosDocenteService;
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
                if (!$candidato || (!$candidato->isDocente() && !$candidato->isAdmin())) {
                    $fail('Solo se pueden añadir docentes o administradores de centro como colaboradores.');
                    return;
                }

                $centroPropietario = $encuentro->docente?->centro_educativo_id;
                if ($centroPropietario === null || $candidato->centro_educativo_id !== $centroPropietario) {
                    $fail('Solo puedes compartir el encuentro con docentes de tu mismo centro educativo.');
                    return;
                }

                // Docentes: solo compañeros que compartan familia y módulo con el proyecto
                /** @var User $candidato */
                /** @var Encuentro $encuentro */
                if ($candidato->isDocente()
                    && !app(ModulosDocenteService::class)->comparteConProyecto($candidato, $encuentro->microproyecto)) {
                    $fail('Solo puedes compartir el encuentro con compañeros que impartan algún módulo de la familia del proyecto.');
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

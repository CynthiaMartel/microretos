<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:254|unique:users,email',
            'password'              => ['required', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|string',
        ];

        // Admin de centro: solo puede crear docentes, asignados automáticamente a su centro
        if ($this->user()->isAdmin()) {
            return $rules;
        }

        // Superadmin: puede crear docente (2), empresa (3) o admin de centro (4)
        return $rules + [
            'role'                => 'required|in:2,3,4',
            'centro_educativo_id' => 'nullable|exists:centro_educativo,id',
            'empresa_id'          => 'nullable|exists:empresas,id',
        ];
    }
}

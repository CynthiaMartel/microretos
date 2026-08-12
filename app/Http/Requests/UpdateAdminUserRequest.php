<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $targetUser = $this->route('user');

        // Admin de centro solo puede mantener el rol docente; superadmin puede asignar 2, 3 o 4
        $rolesPermitidos = $this->user()->isAdmin() ? '2' : '2,3,4';

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:254|unique:users,email,' . $targetUser->id,
            'role'  => 'required|in:' . $rolesPermitidos,
        ];

        if ($this->user()->isSuperAdmin()) {
            $rules['centro_educativo_id'] = 'nullable|exists:centro_educativo,id';
            $rules['empresa_id']          = 'nullable|exists:empresas,id';
        }

        if ($this->filled('password')) {
            $rules['password']              = ['string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()];
            $rules['password_confirmation'] = 'required|string';
            // El superadmin/admin debe confirmar explícitamente que quiere resetear la contraseña de esta cuenta
            $rules['confirm_password_change'] = ['required', 'accepted'];
        }

        return $rules;
    }
}

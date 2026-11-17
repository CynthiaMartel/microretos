<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear un plan de proyecto (POST /planes, P4): a partir de una propuesta del taller que se amplía
 * (origen_uuid) o de otro reto (microreto_id). Pueden crearlo el docente y DuaLab (superadmin).
 * La pertenencia de la propuesta / el reto la comprueba PlanProyectoService.
 */
class StorePlanProyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $limpio = [];
        foreach (['titulo', 'expectativas_empresa'] as $campo) {
            if (is_string($this->input($campo))) {
                $limpio[$campo] = trim(strip_tags($this->input($campo)));
            }
        }
        if ($limpio) $this->merge($limpio);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origen_uuid'          => ['required_without:microreto_id', 'nullable', 'uuid', 'exists:microproyectos,uuid'],
            'microreto_id'         => ['required_without:origen_uuid', 'nullable', 'integer', 'exists:microretos,id'],
            'titulo'               => ['nullable', 'string', 'max:255'],
            'horas_estimadas'      => ['nullable', 'integer', 'between:1,2000'],
            'expectativas_empresa' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'origen_uuid.required_without'  => 'Elige la propuesta que se amplía o el reto del que parte el plan.',
            'microreto_id.required_without' => 'Elige la propuesta que se amplía o el reto del que parte el plan.',
        ];
    }
}

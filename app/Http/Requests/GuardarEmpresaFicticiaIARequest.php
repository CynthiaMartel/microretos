<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarEmpresaFicticiaIARequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        // El token identifica la propuesta (los datos de ficha nunca vienen del cliente).
        // Opcional: el diagnóstico P1–P5 retocado en el paso 2, con los mismos límites que
        // «Editar empresa» (UpdateDiagnosticoEmpresaRequest / StoreEmpresaRequest).
        return [
            'token'              => 'required|uuid',
            'diaANormal'         => 'sometimes|required|string|max:1000',
            'friccionArea'       => 'sometimes|required|string|max:400',
            'friccionProblema'   => 'sometimes|required|string|max:1200',
            'consecuencias'      => 'sometimes|nullable|string|max:2000',
            'restricciones'      => 'sometimes|nullable|string|max:600',
            'loQueNoQuieren'     => 'sometimes|nullable|string|max:500',
            'expectativasAlumno' => 'sometimes|nullable|string|max:800',
        ];
    }

    /**
     * Diagnóstico enviado, ya con nombres de columna.
     *
     * @return array<string, string|null>
     */
    public function diagnostico(): array
    {
        $mapa = [
            'diaANormal' => 'dia_a_normal', 'friccionArea' => 'friccion_area', 'friccionProblema' => 'friccion_problema',
            'consecuencias' => 'consecuencias', 'restricciones' => 'restricciones',
            'loQueNoQuieren' => 'lo_que_no_quieren', 'expectativasAlumno' => 'expectativas_alumno',
        ];
        $datos = $this->validated();
        $columnas = [];
        foreach ($mapa as $campo => $columna) {
            if (array_key_exists($campo, $datos)) $columnas[$columna] = $datos[$campo];
        }
        return $columnas;
    }
}
